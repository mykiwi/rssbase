<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\OAuth;

use App\AtProto\HttpsUrl;
use App\AtProto\Identity\Did;
use App\AtProto\Identity\Handle;
use App\AtProto\Identity\Identity;
use App\AtProto\OAuth\AuthorizationServerLocator;
use App\AtProto\OAuth\ClientIdentity;
use App\AtProto\OAuth\Exception\OAuthError;
use App\AtProto\OAuth\OAuthClient;
use App\AtProto\OAuth\State;
use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use Jose\Component\KeyManagement\JWKFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Http\OAuth2\Dpop\DpopProofFactory;

/**
 * The fake server is only worth its tests if it refuses what a real server refuses.
 */
final class FakeAuthorizationServerTest extends TestCase
{
    private const string LOOPBACK_CLIENT_ID = 'http://localhost?redirect_uri=http%3A%2F%2F127.0.0.1%2Foauth%2Fcallback&scope=atproto%20repo%3A%2A';

    private MockHttpClient $http;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        $this->http = new MockHttpClient(new FakeAtProtoNetwork());
    }

    public function testARequestWithoutDpopProofIsRefused(): void
    {
        $response = $this->http->request('POST', FakeAuthorizationServer::PAR_ENDPOINT, ['body' => [
            'client_id' => self::LOOPBACK_CLIENT_ID,
        ]]);

        static::assertSame(['error' => 'invalid_dpop_proof'], $response->toArray(throw: false));
    }

    public function testARequestWithoutPkceS256IsRefused(): void
    {
        $proofs = new DpopProofFactory(JWKFactory::createECKey('P-256'));
        $nonce = $this->http->request('POST', FakeAuthorizationServer::PAR_ENDPOINT, [
            'headers' => ['DPoP' => $proofs->createProof('POST', FakeAuthorizationServer::PAR_ENDPOINT)],
        ])->getHeaders(throw: false)['dpop-nonce'][0];

        $response = $this->http->request('POST', FakeAuthorizationServer::PAR_ENDPOINT, [
            'headers' => ['DPoP' => $proofs->createProof('POST', FakeAuthorizationServer::PAR_ENDPOINT, nonce: $nonce)],
            'body' => [
                'client_id' => self::LOOPBACK_CLIENT_ID,
                'redirect_uri' => 'http://127.0.0.1/oauth/callback',
                'response_type' => 'code',
                'code_challenge' => str_repeat('a', times: 43),
                'code_challenge_method' => 'plain',
                'state' => 'state-1',
                'scope' => 'atproto repo:*',
            ],
        ]);

        static::assertSame(['error' => 'invalid_request'], $response->toArray(throw: false));
    }

    public function testAConfidentialClientWhoseAssertionTheServerCannotVerifyIsRefused(): void
    {
        $context = new RequestContext(host: 'rssbase.example');
        $clientIdentity = new ClientIdentity(
            json_encode(JWKFactory::createECKey('P-256'), \JSON_THROW_ON_ERROR),
            'atproto repo:*',
            'RssBase',
            $context,
        );
        FakeAuthorizationServer::trustClient(
            $clientIdentity->clientId(),
            JWKFactory::createECKey('P-256')->toPublic()->all(),
        );
        $server = new AuthorizationServerLocator($this->http)->forPds(FakeAtProtoNetwork::pds());

        $this->expectExceptionObject(
            new OAuthError('invalid_client', HttpsUrl::fromString(FakeAuthorizationServer::PAR_ENDPOINT)),
        );

        new OAuthClient($this->http, $clientIdentity)->authorize(
            $server,
            new State('state-1'),
            identity: new Identity(
                Did::fromString(FakeAtProtoNetwork::ALICE_DID),
                Handle::fromString(FakeAtProtoNetwork::ALICE),
                FakeAtProtoNetwork::pds(),
            ),
        );
    }
}
