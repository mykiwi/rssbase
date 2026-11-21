<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\OAuth;

use App\AtProto\JsonObject;
use App\AtProto\OAuth\ClientIdentity;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;

final class ClientIdentityTest extends TestCase
{
    private const int CLOCK_DRIFT = 5;

    public function testALoopbackClientDescribesItselfInItsClientId(): void
    {
        $client = self::client(privateJwk: '', port: 8001);

        static::assertFalse($client->isConfidential());
        static::assertSame('http://127.0.0.1:8001/oauth/callback', $client->redirectUri());
        static::assertSame(
            'http://localhost?redirect_uri=http%3A%2F%2F127.0.0.1%3A8001%2Foauth%2Fcallback&scope=atproto%20repo%3A%2A',
            $client->clientId(),
        );
        static::assertSame(
            ['client_id' => $client->clientId()],
            $client->authenticate([], issuer: 'https://auth.fake.test'),
        );
    }

    public function testALoopbackClientOnTheDefaultPortLeavesThePortOut(): void
    {
        static::assertSame('http://127.0.0.1/oauth/callback', self::client(privateJwk: '', port: 80)->redirectUri());
    }

    public function testAConfidentialClientPublishesItsPublicKeyInItsMetadata(): void
    {
        $key = JWKFactory::createECKey('P-256');
        $client = self::client(json_encode($key, \JSON_THROW_ON_ERROR), port: 8001);

        static::assertTrue($client->isConfidential());
        static::assertSame('https://rssbase.example/oauth-client-metadata.json', $client->clientId());
        static::assertSame('https://rssbase.example/oauth/callback', $client->redirectUri());

        $metadata = $client->metadata();
        static::assertSame($client->clientId(), $metadata['client_id']);
        static::assertSame(['https://rssbase.example/oauth/callback'], $metadata['redirect_uris']);
        static::assertSame('atproto repo:*', $metadata['scope']);
        static::assertSame('private_key_jwt', $metadata['token_endpoint_auth_method']);
        static::assertSame('ES256', $metadata['token_endpoint_auth_signing_alg']);
        static::assertTrue($metadata['dpop_bound_access_tokens']);
        static::assertSame(
            [
                'keys' => [[
                    'kid' => $key->thumbprint('sha256'),
                    'kty' => 'EC',
                    'crv' => 'P-256',
                    'x' => $key->get('x'),
                    'y' => $key->get('y'),
                    'use' => 'sig',
                    'alg' => 'ES256',
                ]],
            ],
            $metadata['jwks'],
        );
    }

    public function testAConfidentialClientSignsAnAssertionForTheIssuer(): void
    {
        $key = JWKFactory::createECKey('P-256', ['kid' => 'key-1']);
        $client = self::client(json_encode($key, \JSON_THROW_ON_ERROR), port: 443);

        $body = $client->authenticate(['grant_type' => 'refresh_token'], issuer: 'https://auth.fake.test');
        $second = $client->authenticate([], issuer: 'https://auth.fake.test');

        static::assertSame('refresh_token', $body['grant_type']);
        static::assertSame('https://rssbase.example/oauth-client-metadata.json', $body['client_id']);
        static::assertSame('urn:ietf:params:oauth:client-assertion-type:jwt-bearer', $body['client_assertion_type']);

        $jws = new CompactSerializer()->unserialize($body['client_assertion']);
        static::assertTrue(new JWSVerifier(new AlgorithmManager([new ES256()]))->verifyWithKey(
            $jws,
            $key->toPublic(),
            signature: 0,
        ));
        static::assertSame(['alg' => 'ES256', 'kid' => 'key-1'], $jws->getSignature(0)->getProtectedHeader());

        $claims = self::claims($body['client_assertion']);
        static::assertSame($body['client_id'], $claims->string('iss'));
        static::assertSame($body['client_id'], $claims->string('sub'));
        static::assertSame('https://auth.fake.test', $claims->string('aud'));
        static::assertEqualsWithDelta(time(), $claims->int('iat'), self::CLOCK_DRIFT);
        static::assertNotSame($claims->string('jti'), self::claims($second['client_assertion'])->string('jti'));
    }

    public function testAKeyThatIsNotAnEs256PrivateKeyIsRefused(): void
    {
        $client = self::client(
            json_encode(JWKFactory::createECKey('P-256')->toPublic(), \JSON_THROW_ON_ERROR),
            port: 443,
        );

        $this->expectException(\InvalidArgumentException::class);

        $client->metadata();
    }

    private static function claims(string $jwt): JsonObject
    {
        return JsonObject::fromJson(
            (string) new CompactSerializer()
                ->unserialize($jwt)
                ->getPayload(),
        );
    }

    private static function client(string $privateJwk, int $port): ClientIdentity
    {
        $context = new RequestContext(host: 'fr.rssbase.example', httpPort: $port);
        $context->setParameter('domain', parameter: 'rssbase.example');

        return new ClientIdentity($privateJwk, scope: 'atproto repo:*', name: 'RssBase', requestContext: $context);
    }
}
