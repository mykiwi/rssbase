<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The callback sends the answer of a provider to the site the visitor started from, and to no other.
 */
final class OAuthCallbackTest extends WebTestCase
{
    use SignsInThroughTheFakeProvider;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        $this->client = static::createClient();
    }

    public function testTheAnswerGoesToTheOtherLanguageOfTheSameDomain(): void
    {
        $state = $this->stateIssuedOn('https://fr.rssbase.example');

        $this->client->request('GET', uri: "https://rssbase.example/oauth/callback?code=c&iss=i&state={$state}");

        self::assertResponseRedirects("https://fr.rssbase.example/oauth/callback?code=c&iss=i&state={$state}");
    }

    public function testTheAnswerDoesNotGoToAnotherDomain(): void
    {
        $state = $this->stateIssuedOn('https://evil.example');

        $this->client->request('GET', uri: "https://rssbase.example/oauth/callback?code=c&iss=i&state={$state}");

        self::assertResponseRedirects('/login');
    }

    public function testTheLoopbackCallbackOnlySendsTheAnswerToALocalSite(): void
    {
        $state = $this->stateIssuedOn('http://evil.example');

        $this->client->request('GET', uri: "http://127.0.0.1/oauth/callback?code=c&iss=i&state={$state}");

        self::assertResponseRedirects('/login');
    }

    /**
     * Anyone can ask for a login with the Host header of their choice, and reads the state in the
     * address their provider sends them back to.
     */
    private function stateIssuedOn(string $site): string
    {
        $authorizationUrl = self::submitHandle($this->client, FakeAtProtoNetwork::ALICE, $site);
        $state = FakeAuthorizationServer::pushedRequest($authorizationUrl)['state'] ?? '';
        static::assertNotSame('', $state);
        $this->client->getCookieJar()->clear();

        return rawurlencode($state);
    }
}
