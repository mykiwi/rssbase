<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\AtProtoUser;
use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class SessionTest extends WebTestCase
{
    use SignsInThroughTheFakeProvider;

    /** Browsers refuse a cookie for "localhost": the languages share a session on a domain of two labels. */
    private const string SITE = 'http://rssbase.localhost';
    private const string FRENCH_SITE = 'http://fr.rssbase.localhost';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        $this->client = static::createClient();
    }

    public function testASessionServesBothLanguages(): void
    {
        $this->signIn(self::FRENCH_SITE);
        static::assertSame(self::FRENCH_SITE . '/feeds', $this->client->getRequest()->getUri());

        $this->client->request('GET', self::SITE . '/feeds');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', text: 'My feeds');

        $this->client->clickLink('Français');
        static::assertSame(self::FRENCH_SITE . '/feeds', $this->client->getRequest()->getUri());
        self::assertSelectorTextSame('h1', text: 'Mes flux');
    }

    public function testTheSessionCookieOutlivesTheBrowser(): void
    {
        $this->signIn(self::SITE);

        $cookie = $this->client->getCookieJar()->get('MOCKSESSID', path: '/', domain: 'rssbase.localhost');
        static::assertNotNull($cookie);
        static::assertSame('rssbase.localhost', ltrim($cookie->getDomain(), characters: '.'));
        static::assertGreaterThan(time() + (89 * 86_400), (int) $cookie->getExpiresTime());
    }

    public function testSigningOutEndsTheSessionOfThisDeviceOnly(): void
    {
        $this->signIn(self::SITE);
        $firstDevice = $this->client->getCookieJar()->all();
        $this->client->getCookieJar()->clear();
        $this->signIn(self::SITE);

        $this->client->submitForm('Sign out');
        self::assertResponseRedirects(self::SITE . '/');
        $this->client->request('GET', self::SITE . '/feeds');
        self::assertResponseRedirects('/login');

        $this->client->getCookieJar()->clear();
        foreach ($firstDevice as $cookie) {
            $this->client->getCookieJar()->set($cookie);
        }
        $this->client->request('GET', self::SITE . '/feeds');
        self::assertResponseIsSuccessful();
    }

    public function testAnExpiredAccessTokenIsRefreshedWithoutTheUserNoticing(): void
    {
        FakeAuthorizationServer::issueAccessTokensFor(0);
        $this->signIn(self::SITE);
        $refreshes = self::tokenRequests();

        $this->client->request('GET', self::SITE . '/feeds');

        self::assertResponseIsSuccessful();
        static::assertSame($refreshes + 1, self::tokenRequests());
    }

    public function testARevokedAccessEndsTheSessionWithAMessage(): void
    {
        FakeAuthorizationServer::issueAccessTokensFor(0);
        $this->signIn(self::SITE);
        FakeAuthorizationServer::revoke(FakeAtProtoNetwork::ALICE_DID);

        $this->client->request('GET', self::SITE . '/feeds');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextSame(
            '#handle-error',
            text: 'Your provider ended your session. Sign in again to continue.',
        );
        $this->client->request('GET', self::SITE . '/feeds');
        self::assertResponseRedirects('/login');
    }

    public function testAnUnreachableProviderKeepsTheUserSignedIn(): void
    {
        FakeAuthorizationServer::issueAccessTokensFor(0);
        $this->signIn(self::SITE);
        FakeAtProtoNetwork::makeUnreachable('auth.fake.test');

        $this->client->request('GET', self::SITE . '/feeds');

        self::assertResponseIsSuccessful();
    }

    public function testAnAccountWithoutTheWritePermissionSeesTheSameFeeds(): void
    {
        FakeAuthorizationServer::grantOnly('atproto');
        $this->signIn(self::SITE);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', text: 'My feeds');
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        static::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $user = $tokenStorage->getToken()?->getUser();
        static::assertInstanceOf(AtProtoUser::class, $user);
        static::assertFalse($user->canWrite);
    }

    private function signIn(string $site): void
    {
        self::answer(
            $this->client,
            self::submitHandle($this->client, FakeAtProtoNetwork::ALICE, $site),
            FakeAtProtoNetwork::ALICE_DID,
        );
    }

    private static function tokenRequests(): int
    {
        return \count(array_keys(
            FakeAtProtoNetwork::requests(),
            'POST ' . FakeAuthorizationServer::TOKEN_ENDPOINT,
            strict: true,
        ));
    }
}
