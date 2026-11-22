<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\AtProto\Identity\Did;
use App\AtProto\Identity\Handle;
use App\Security\AtProtoUser;
use App\Tests\Fake\FakeAtProtoNetwork;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginTest extends WebTestCase
{
    use SignsInThroughTheFakeProvider;

    private const int CLOCK_TICK = 2;

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

        // The operator finds the handle and the date of the login in the trace.
        $connection = self::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);
        $trace = $connection->fetchAssociative('SELECT handle, unixepoch(last_login_at) AS last_login FROM account WHERE did = ?', [
            $did,
        ]);
        static::assertIsArray($trace);
        static::assertSame($handle, $trace['handle']);
        static::assertEqualsWithDelta(time(), $trace['last_login'], self::CLOCK_TICK);
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
        $client->loginUser(
            new AtProtoUser(
                Did::fromString(FakeAtProtoNetwork::ALICE_DID),
                Handle::fromString(FakeAtProtoNetwork::ALICE),
            ),
        );

        foreach (['/', '/login'] as $page) {
            $client->request('GET', $page);
            self::assertResponseRedirects('/feeds');
        }
    }

    public function testASignedInUserSeesMyFeeds(): void
    {
        $client = static::createClient();
        $client->loginUser(
            new AtProtoUser(
                Did::fromString(FakeAtProtoNetwork::ALICE_DID),
                Handle::fromString(FakeAtProtoNetwork::ALICE),
            ),
        );
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
