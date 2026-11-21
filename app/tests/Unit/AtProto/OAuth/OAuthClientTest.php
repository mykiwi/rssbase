<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\OAuth;

use App\AtProto\Identity\Handle;
use App\AtProto\Identity\Identity;
use App\AtProto\OAuth\AuthorizationServer;
use App\AtProto\OAuth\AuthorizationServerLocator;
use App\AtProto\OAuth\ClientIdentity;
use App\AtProto\OAuth\Exception\OAuthError;
use App\AtProto\OAuth\OAuthClient;
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
        $this->server = new AuthorizationServerLocator($this->http)->forPds(FakeAtProtoNetwork::PDS);
        $this->alice = new Identity(
            FakeAtProtoNetwork::ALICE_DID,
            Handle::fromString(FakeAtProtoNetwork::ALICE),
            FakeAtProtoNetwork::PDS,
        );
    }

    public function testALoopbackClientSignsInAndRefreshesItsTokens(): void
    {
        $client = $this->client(privateJwk: '');
        [$pending, $url] = $client->authorize($this->server, state: 'state-1', identity: $this->alice);

        static::assertSame(FakeAuthorizationServer::ISSUER, $this->server->issuer);
        static::assertSame(FakeAtProtoNetwork::ALICE, FakeAuthorizationServer::pushedRequest($url)['login_hint']);
        // The server asks for its nonce on the first request: the client sends the request once more.
        static::assertCount(2, array_keys(
            FakeAtProtoNetwork::requests(),
            'POST ' . FakeAuthorizationServer::PAR_ENDPOINT,
            strict: true,
        ));

        $tokens = $client->exchangeCode($pending, self::code($url));
        static::assertSame(FakeAtProtoNetwork::ALICE_DID, $tokens->did);
        static::assertSame('atproto repo:*', $tokens->scope);
        static::assertTrue($tokens->grantsWrite());

        $refreshed = $client->refresh($tokens);
        static::assertNotSame($tokens->refreshToken(), $refreshed->refreshToken());

        // Refresh tokens are single-use.
        $this->expectExceptionObject(new OAuthError('invalid_grant', FakeAuthorizationServer::TOKEN_ENDPOINT));
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

        [$pending, $url] = $client->authorize($this->server, state: 'state-1', identity: $this->alice);

        static::assertSame(FakeAtProtoNetwork::ALICE_DID, $client->exchangeCode($pending, self::code($url))->did);
    }

    public function testStartingFromAProviderAsksToCreateAnAccount(): void
    {
        [$pending, $url] = $this->client(privateJwk: '')->authorize($this->server, state: 'state-1', identity: null);
        $request = FakeAuthorizationServer::pushedRequest($url);

        static::assertNull($pending->identity);
        static::assertSame('create', $request['prompt']);
        static::assertArrayNotHasKey('login_hint', $request);
    }

    public function testAProviderThatDropsTheWritePermissionGrantsTheIdentityOnly(): void
    {
        FakeAuthorizationServer::grantOnly('atproto');

        static::assertFalse($this->signIn()->grantsWrite());
    }

    public function testARevokedAccessCannotBeRefreshed(): void
    {
        $tokens = $this->signIn();
        FakeAuthorizationServer::revoke(FakeAtProtoNetwork::ALICE_DID);

        $this->expectExceptionObject(new OAuthError('invalid_grant', FakeAuthorizationServer::TOKEN_ENDPOINT));

        $this->client(privateJwk: '')->refresh($tokens);
    }

    private function signIn(): TokenSet
    {
        $client = $this->client(privateJwk: '');
        [$pending, $url] = $client->authorize($this->server, state: 'state-1', identity: $this->alice);

        return $client->exchangeCode($pending, self::code($url));
    }

    private function client(string $privateJwk): OAuthClient
    {
        $context = new RequestContext(host: 'rssbase.example');
        $context->setParameter('domain', parameter: 'rssbase.example');

        return new OAuthClient($this->http, new ClientIdentity($privateJwk, 'atproto repo:*', 'RssBase', $context));
    }

    private static function code(string $authorizationUrl): string
    {
        $query = [];
        parse_str(
            (string) parse_url(
                FakeAuthorizationServer::answer($authorizationUrl, FakeAtProtoNetwork::ALICE_DID),
                \PHP_URL_QUERY,
            ),
            $query,
        );

        return \is_string($query['code'] ?? null) ? $query['code'] : '';
    }
}
