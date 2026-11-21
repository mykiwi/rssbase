<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Jose\Component\KeyManagement\JWKFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ClientMetadataTest extends WebTestCase
{
    protected function tearDown(): void
    {
        self::useClientKey('');

        parent::tearDown();
    }

    public function testAConfidentialClientPublishesItsMetadataWithItsPublicKeyOnly(): void
    {
        $key = JWKFactory::createECKey('P-256', ['kid' => 'key-1']);
        self::useClientKey(json_encode($key, \JSON_THROW_ON_ERROR));

        $client = static::createClient();
        // Authorization servers read it from the English site, whatever the language the visitor uses.
        $client->request('GET', uri: 'https://fr.rssbase.example/oauth-client-metadata.json');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', expectedValue: 'application/json');
        static::assertSame(
            [
                'client_id' => 'https://rssbase.example/oauth-client-metadata.json',
                'client_name' => 'RssBase',
                'client_uri' => 'https://rssbase.example',
                'application_type' => 'web',
                'grant_types' => ['authorization_code', 'refresh_token'],
                'response_types' => ['code'],
                'redirect_uris' => ['https://rssbase.example/oauth/callback'],
                'scope' => 'atproto repo:*',
                'token_endpoint_auth_method' => 'private_key_jwt',
                'token_endpoint_auth_signing_alg' => 'ES256',
                'dpop_bound_access_tokens' => true,
                'jwks' => [
                    'keys' => [[
                        'kid' => 'key-1',
                        'kty' => 'EC',
                        'crv' => 'P-256',
                        'x' => $key->get('x'),
                        'y' => $key->get('y'),
                        'use' => 'sig',
                        'alg' => 'ES256',
                    ]],
                ],
            ],
            json_decode((string) $client->getResponse()->getContent(), associative: true, flags: \JSON_THROW_ON_ERROR),
        );
        static::assertStringNotContainsString((string) $key->get('d'), (string) $client->getResponse()->getContent());
    }

    public function testALoopbackClientHasNoMetadataDocument(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/oauth-client-metadata.json');

        self::assertResponseStatusCodeSame(404);
    }

    private static function useClientKey(string $privateJwk): void
    {
        $_ENV['ATPROTO_CLIENT_PRIVATE_JWK'] = $privateJwk;
        $_SERVER['ATPROTO_CLIENT_PRIVATE_JWK'] = $privateJwk;
    }
}
