<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use Jose\Component\KeyManagement\JWKFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * What a signed-in user sees when the tokens of their session cannot be refreshed.
 */
final class RefreshFailureTest extends WebTestCase
{
    use SignsInThroughTheFakeProvider;

    private const string CLIENT_ID = 'https://rssbase.example/oauth-client-metadata.json';

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        // Access tokens that expire at once: every request of a signed-in user refreshes them.
        FakeAuthorizationServer::issueAccessTokensFor(0);
    }

    protected function tearDown(): void
    {
        self::useClientKey('');

        parent::tearDown();
    }

    public function testAnUnreachableProviderIsNotAskedAgainRightAway(): void
    {
        $client = static::createClient();
        self::answer(
            $client,
            self::submitHandle($client, FakeAtProtoNetwork::ALICE, site: 'http://rssbase.localhost'),
            FakeAtProtoNetwork::ALICE_DID,
        );
        FakeAtProtoNetwork::makeUnreachable('auth.fake.test');
        $client->request('GET', uri: 'http://rssbase.localhost/feeds');
        $attempts = self::tokenRequests();

        $client->request('GET', uri: 'http://rssbase.localhost/feeds');

        self::assertResponseIsSuccessful();
        static::assertSame($attempts, self::tokenRequests());
    }

    public function testAProviderThatNoLongerKnowsTheKeyOfTheClientEndsTheSession(): void
    {
        $key = JWKFactory::createECKey('P-256', ['kid' => 'key-1']);
        self::useClientKey(json_encode($key, \JSON_THROW_ON_ERROR));
        FakeAuthorizationServer::trustClient(self::CLIENT_ID, $key->toPublic()->all());
        $client = static::createClient();
        self::answer(
            $client,
            self::submitHandle($client, FakeAtProtoNetwork::ALICE, site: 'https://rssbase.example'),
            FakeAtProtoNetwork::ALICE_DID,
        );
        static::assertSame('https://rssbase.example/feeds', $client->getRequest()->getUri());
        self::assertResponseIsSuccessful();

        FakeAuthorizationServer::trustClient(
            self::CLIENT_ID,
            JWKFactory::createECKey('P-256', ['kid' => 'key-2'])->toPublic()->all(),
        );
        $client->request('GET', uri: 'https://rssbase.example/feeds');

        self::assertResponseRedirects('/login');
        $attempts = self::tokenRequests();
        $client->request('GET', uri: 'https://rssbase.example/feeds');
        self::assertResponseRedirects('/login');
        static::assertSame($attempts, self::tokenRequests());
    }

    private static function tokenRequests(): int
    {
        return \count(array_keys(
            FakeAtProtoNetwork::requests(),
            'POST ' . FakeAuthorizationServer::TOKEN_ENDPOINT,
            strict: true,
        ));
    }

    private static function useClientKey(string $privateJwk): void
    {
        $_ENV['ATPROTO_CLIENT_PRIVATE_JWK'] = $privateJwk;
        $_SERVER['ATPROTO_CLIENT_PRIVATE_JWK'] = $privateJwk;
    }
}
