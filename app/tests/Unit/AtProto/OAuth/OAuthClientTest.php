<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\OAuth;

use App\AtProto\HttpsUrl;
use App\AtProto\Identity\Did;
use App\AtProto\Identity\Handle;
use App\AtProto\Identity\Identity;
use App\AtProto\OAuth\AuthorizationServer;
use App\AtProto\OAuth\AuthorizationServerLocator;
use App\AtProto\OAuth\ClientIdentity;
use App\AtProto\OAuth\Exception\OAuthError;
use App\AtProto\OAuth\OAuthClient;
use App\AtProto\OAuth\PushedAuthorization;
use App\AtProto\OAuth\State;
use App\AtProto\OAuth\TokenSet;
use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use Jose\Component\KeyManagement\JWKFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Routing\RequestContext;

final class OAuthClientTest extends TestCase
{
    private MockHttpClient $http;
    private AuthorizationServer $server;
    private Identity $alice;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        $this->http = new MockHttpClient(new FakeAtProtoNetwork());
        $this->server = new AuthorizationServerLocator($this->http)->forPds(FakeAtProtoNetwork::pds());
        $this->alice = new Identity(
            Did::fromString(FakeAtProtoNetwork::ALICE_DID),
            Handle::fromString(FakeAtProtoNetwork::ALICE),
            FakeAtProtoNetwork::pds(),
        );
    }

    public function testALoopbackClientSignsInAndRefreshesItsTokens(): void
    {
        $client = $this->client(privateJwk: '');
        $pushed = $client->authorize($this->server, new State('state-1'), identity: $this->alice);

        static::assertSame(FakeAuthorizationServer::ISSUER, $this->server->issuer->value);
        static::assertSame(
            FakeAtProtoNetwork::ALICE,
            FakeAuthorizationServer::pushedRequest($pushed->redirectUrl->value)['login_hint'],
        );
        // The server asks for its nonce on the first request: the client sends the request once more.
        static::assertCount(2, array_keys(
            FakeAtProtoNetwork::requests(),
            'POST ' . FakeAuthorizationServer::PAR_ENDPOINT,
            strict: true,
        ));

        $tokens = $client->exchangeCode($pushed->pending, self::code($pushed));
        static::assertSame(FakeAtProtoNetwork::ALICE_DID, $tokens->did->value);
        static::assertSame('atproto repo:*', $tokens->scope->value);
        static::assertTrue($tokens->scope->grantsWrite());

        $refreshed = $client->refresh($tokens);
        static::assertNotSame($tokens->refreshToken(), $refreshed->refreshToken());

        // Refresh tokens are single-use.
        $this->expectExceptionObject(
            new OAuthError('invalid_grant', HttpsUrl::fromString(FakeAuthorizationServer::TOKEN_ENDPOINT)),
        );
        $client->refresh($tokens);
    }

    public function testAConfidentialClientSignsInWithItsAssertion(): void
    {
        $key = JWKFactory::createECKey('P-256', ['kid' => 'key-1']);
        $client = $this->client(json_encode($key, \JSON_THROW_ON_ERROR));
        FakeAuthorizationServer::trustClient(
            'https://rssbase.example/oauth-client-metadata.json',
            $key->toPublic()->all(),
        );

        $pushed = $client->authorize($this->server, new State('state-1'), identity: $this->alice);

        static::assertSame(
            FakeAtProtoNetwork::ALICE_DID,
            $client->exchangeCode($pushed->pending, self::code($pushed))->did->value,
        );
    }

    public function testStartingFromAProviderAsksToCreateAnAccount(): void
    {
        $pushed = $this->client(privateJwk: '')->authorize($this->server, new State('state-1'), identity: null);
        $request = FakeAuthorizationServer::pushedRequest($pushed->redirectUrl->value);

        static::assertNull($pushed->pending->identity);
        static::assertSame('create', $request['prompt']);
        static::assertArrayNotHasKey('login_hint', $request);
    }

    public function testAProviderThatDropsTheWritePermissionGrantsTheIdentityOnly(): void
    {
        FakeAuthorizationServer::grantOnly('atproto');

        static::assertFalse($this->signIn()->scope->grantsWrite());
    }

    public function testARevokedAccessCannotBeRefreshed(): void
    {
        $tokens = $this->signIn();
        FakeAuthorizationServer::revoke(FakeAtProtoNetwork::ALICE_DID);

        $this->expectExceptionObject(
            new OAuthError('invalid_grant', HttpsUrl::fromString(FakeAuthorizationServer::TOKEN_ENDPOINT)),
        );

        $this->client(privateJwk: '')->refresh($tokens);
    }

    private function signIn(): TokenSet
    {
        $client = $this->client(privateJwk: '');
        $pushed = $client->authorize($this->server, new State('state-1'), identity: $this->alice);

        return $client->exchangeCode($pushed->pending, self::code($pushed));
    }

    private function client(string $privateJwk): OAuthClient
    {
        $context = new RequestContext(host: 'rssbase.example');
        $context->setParameter('domain', parameter: 'rssbase.example');

        return new OAuthClient($this->http, new ClientIdentity($privateJwk, 'atproto repo:*', 'RssBase', $context));
    }

    private static function code(PushedAuthorization $pushed): string
    {
        $query = [];
        parse_str(
            (string) parse_url(
                FakeAuthorizationServer::answer($pushed->redirectUrl->value, FakeAtProtoNetwork::ALICE_DID),
                \PHP_URL_QUERY,
            ),
            $query,
        );

        return \is_string($query['code'] ?? null) ? $query['code'] : '';
    }
}
