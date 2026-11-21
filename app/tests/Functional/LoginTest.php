<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\AtProtoUser;
use App\Tests\Fake\FakeAtProtoNetwork;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginTest extends WebTestCase
{
    use SignsInThroughTheFakeProvider;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
    }

    public function testStartOpensTheLoginPageWhichAsksForNoPassword(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/');
        $client->clickLink('Start');

        self::assertResponseIsSuccessful();
        static::assertSame('http://localhost/login', $client->getRequest()->getUri());
        self::assertSelectorTextSame('h1', text: 'Sign in');
        self::assertSelectorExists('input[name="handle"]');
        self::assertSelectorNotExists('input[type="password"]');
    }

    #[TestWith([FakeAtProtoNetwork::ALICE, FakeAtProtoNetwork::ALICE_DID, FakeAtProtoNetwork::ALICE])]
    #[TestWith(['  @Alice.Fake.Test ', FakeAtProtoNetwork::ALICE_DID, FakeAtProtoNetwork::ALICE])]
    #[TestWith(['bob', FakeAtProtoNetwork::BOB_DID, FakeAtProtoNetwork::BOB])]
    public function testAVisitorSignsInWithTheirHandleAndArrivesOnMyFeeds(
        string $input,
        string $did,
        string $handle,
    ): void {
        $client = static::createClient();
        $authorizationUrl = self::submitHandle($client, $input);

        static::assertStringStartsWith('https://auth.fake.test/oauth/authorize?', $authorizationUrl);
        self::answer($client, $authorizationUrl, $did);

        self::assertResponseIsSuccessful();
        static::assertSame('http://localhost/feeds', $client->getRequest()->getUri());
        self::assertSelectorTextSame('[data-test="account-handle"]', text: "@{$handle}");
    }

    public function testAVisitorWhoStartsOnTheFrenchSiteComesBackToIt(): void
    {
        $client = static::createClient();
        self::answer(
            $client,
            self::submitHandle($client, FakeAtProtoNetwork::ALICE, site: 'http://fr.localhost'),
            FakeAtProtoNetwork::ALICE_DID,
        );

        static::assertSame('http://fr.localhost/feeds', $client->getRequest()->getUri());
        self::assertSelectorTextSame('h1', text: 'Mes flux');
    }

    public function testAVisitorWhoOpensMyFeedsSignsInAndComesBackToIt(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/feeds');
        self::assertResponseRedirects('/login');

        self::answer($client, self::submitHandle($client, FakeAtProtoNetwork::ALICE), FakeAtProtoNetwork::ALICE_DID);

        static::assertSame('http://localhost/feeds', $client->getRequest()->getUri());
    }

    public function testASignedInUserWhoOpensTheHomeOrTheLoginPageIsSentToMyFeeds(): void
    {
        $client = static::createClient();
        $client->loginUser(new AtProtoUser(FakeAtProtoNetwork::ALICE_DID, FakeAtProtoNetwork::ALICE));

        foreach (['/', '/login'] as $page) {
            $client->request('GET', $page);
            self::assertResponseRedirects('/feeds');
        }
    }

    public function testASignedInUserSeesMyFeeds(): void
    {
        $client = static::createClient();
        $client->loginUser(new AtProtoUser(FakeAtProtoNetwork::ALICE_DID, FakeAtProtoNetwork::ALICE));
        $client->request('GET', uri: '/feeds');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', text: 'My feeds');
        self::assertSelectorTextSame('[data-test="feeds-empty"] h2', text: 'You don’t follow any feed yet');
    }

    public function testAnUnknownUrlIsStillNotFoundForAVisitor(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }
}
