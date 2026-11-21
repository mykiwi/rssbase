<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\AtProtoUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginTest extends WebTestCase
{
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

    public function testAVisitorWhoOpensMyFeedsIsSentToTheLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/feeds');

        self::assertResponseRedirects('/login');
    }

    public function testASignedInUserSeesMyFeeds(): void
    {
        $client = static::createClient();
        $client->loginUser(new AtProtoUser('did:plc:alice', 'alice.fake.test'));
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
