<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\AtProto\Identity\Did;
use App\AtProto\Identity\Handle;
use App\Security\AtProtoUser;
use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProviderTest extends WebTestCase
{
    use SignsInThroughTheFakeProvider;

    private const string NEW_HANDLE = 'dana.fake.test';
    private const string NEW_DID = 'did:plc:dana234567abcdefghijklmn';

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
    }

    public function testTheLoginPageLeadsToTheProvidersWithTheirLabelAndRegion(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/login');
        $client->clickLink('Choose a provider');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', text: 'Choose a provider');
        self::assertSelectorTextContains(
            'main > p',
            text: 'You can move to another one later without losing anything.',
        );
        static::assertSame(
            [
                'Eurosky Recommended Hosted in the EU',
                'Bluesky Most popular Hosted in the US',
                'Blacksky Hosted in the US',
                'NPMX Hosted in the EU',
            ],
            $client
                ->getCrawler()
                ->filter('[data-test="providers"] button > span:first-child')
                ->each(static fn($node): string => (string) preg_replace(
                    '/\s+/',
                    replacement: ' ',
                    subject: trim($node->text()),
                )),
        );
    }

    /**
     * @param list<string> $providers
     */
    #[TestWith(['All', ['Eurosky', 'Bluesky', 'Blacksky', 'NPMX']])]
    #[TestWith(['EU', ['Eurosky', 'NPMX']])]
    #[TestWith(['US', ['Bluesky', 'Blacksky']])]
    public function testTheFilterKeepsTheProvidersOfARegion(string $filter, array $providers): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/providers?region=eu');
        $client->clickLink($filter);

        self::assertSelectorTextSame('[data-test="provider-filter"] [aria-current="page"]', text: $filter);
        static::assertSame($providers, $client
            ->getCrawler()
            ->filter('[data-test="provider-name"]')
            ->each(static fn($node): string => $node->text()));
    }

    public function testTheScreenLinksToAllProvidersToSelfHostingAndBackToTheLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/providers');

        self::assertSelectorExists('a[href="https://atmosphereaccount.com/hosts"]');
        self::assertSelectorTextContains('a[href="https://atmosphereaccount.com/hosts"]', text: 'Browse all providers');
        self::assertSelectorTextContains('a[href="https://atproto.com/guides/self-hosting"]', text: 'Host your own');

        $client->clickLink('Sign in');
        self::assertSelectorExists('input[name="handle"]');
    }

    public function testChoosingAProviderSendsTheVisitorStraightToItAndBackSignedIn(): void
    {
        FakeAtProtoNetwork::addAccount(self::NEW_HANDLE, self::NEW_DID);
        $client = static::createClient();
        $client->request('GET', uri: '/providers');
        $client->submitForm('Eurosky');

        $authorizationUrl = (string) $client->getResponse()->headers->get('Location');
        $request = FakeAuthorizationServer::pushedRequest($authorizationUrl);
        static::assertSame('create', $request['prompt']);
        static::assertArrayNotHasKey('login_hint', $request);

        self::answer($client, $authorizationUrl, self::NEW_DID);

        static::assertSame('http://localhost/feeds', $client->getRequest()->getUri());
        self::assertSelectorTextSame('[data-test="account-handle"]', text: '@' . self::NEW_HANDLE);
    }

    public function testAnAccountHostedByAnotherServerThanTheChosenProviderIsRefused(): void
    {
        FakeAtProtoNetwork::addAccount(self::NEW_HANDLE, self::NEW_DID, pds: 'https://elsewhere.fake.test');
        FakeAtProtoNetwork::publish('https://elsewhere.fake.test/.well-known/oauth-protected-resource', [
            'authorization_servers' => ['https://auth.elsewhere.fake.test'],
        ]);
        $client = static::createClient();
        $client->request('GET', uri: '/providers');
        $client->submitForm('Eurosky');

        self::answer($client, (string) $client->getResponse()->headers->get('Location'), self::NEW_DID);

        static::assertSame('http://localhost/login', $client->getRequest()->getUri());
        self::assertSelectorTextSame('#handle-error', text: 'The sign-in didn’t complete. Try again.');
    }

    public function testASignedInUserWhoOpensTheProvidersIsSentToMyFeeds(): void
    {
        $client = static::createClient();
        $client->loginUser(
            new AtProtoUser(
                Did::fromString(FakeAtProtoNetwork::ALICE_DID),
                Handle::fromString(FakeAtProtoNetwork::ALICE),
            ),
        );
        $client->request('GET', uri: '/providers');

        self::assertResponseRedirects('/feeds');
    }
}
