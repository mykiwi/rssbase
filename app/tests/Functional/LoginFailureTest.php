<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginFailureTest extends WebTestCase
{
    use SignsInThroughTheFakeProvider;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
    }

    public function testAnUnknownHandleKeepsTheInputAndSaysTheAccountIsNotFound(): void
    {
        $client = static::createClient();
        self::submitHandle($client, handle: 'nobody.fake.test');

        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('handle', expectedValue: 'nobody.fake.test');
        self::assertSelectorTextSame(
            '#handle-error',
            text: 'No account has this handle. Check how it’s spelled, then try again.',
        );
        self::assertSelectorExists('input[aria-invalid="true"][aria-describedby="handle-error"]');
    }

    public function testAnUnreachableProviderKeepsTheInputAndSaysSo(): void
    {
        FakeAtProtoNetwork::makeUnreachable('auth.fake.test');
        $client = static::createClient();
        self::submitHandle($client, FakeAtProtoNetwork::ALICE);

        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('handle', FakeAtProtoNetwork::ALICE);
        self::assertSelectorTextSame(
            '#handle-error',
            text: 'Your provider can’t be reached right now. Try again in a moment.',
        );
    }

    public function testARefusalAtTheProviderKeepsTheHandleAndSaysSo(): void
    {
        $client = static::createClient();
        self::answer($client, self::submitHandle($client, FakeAtProtoNetwork::ALICE), did: null);

        static::assertSame('http://localhost/login', $client->getRequest()->getUri());
        self::assertInputValueSame('handle', FakeAtProtoNetwork::ALICE);
        self::assertSelectorTextSame(
            '#handle-error',
            text: 'You didn’t approve RssBase at your provider, so you aren’t signed in.',
        );
    }

    public function testAnotherAccountThanTheOneTypedIsRefused(): void
    {
        $client = static::createClient();
        self::answer($client, self::submitHandle($client, FakeAtProtoNetwork::ALICE), FakeAtProtoNetwork::BOB_DID);

        self::assertSignInFailed($client);
    }

    public function testAnAnswerFromAnotherIssuerIsRefused(): void
    {
        $client = static::createClient();
        $callback = FakeAuthorizationServer::answer(
            self::submitHandle($client, FakeAtProtoNetwork::ALICE),
            FakeAtProtoNetwork::ALICE_DID,
        );
        $client->request('GET', str_replace(
            urlencode(FakeAuthorizationServer::ISSUER),
            urlencode('https://evil.fake.test'),
            $callback,
        ));
        $client->followRedirect();
        $client->followRedirect();

        self::assertSignInFailed($client);
    }

    public function testAForgedStateIsRefused(): void
    {
        $client = static::createClient();
        self::submitHandle($client, FakeAtProtoNetwork::ALICE);
        $client->request(
            'GET',
            uri: 'http://localhost/oauth/callback?code=code-1&state=forged&iss=https%3A%2F%2Fauth.fake.test',
        );
        $client->followRedirect();

        self::assertSignInFailed($client);
    }

    public function testAnAnswerIsAcceptedOnce(): void
    {
        $client = static::createClient();
        $callback = FakeAuthorizationServer::answer(
            self::submitHandle($client, FakeAtProtoNetwork::ALICE),
            FakeAtProtoNetwork::ALICE_DID,
        );
        $client->request('GET', $callback);
        $replay = (string) $client->getResponse()->headers->get('Location');
        $client->request('GET', $replay);
        self::assertResponseRedirects('/feeds');

        $exchanges = self::tokenRequests();

        // The request the answer belongs to left the session with the first use: the code is not
        // sent to the provider a second time.
        $client->request('GET', $replay);

        self::assertResponseRedirects('/login');
        static::assertSame($exchanges, self::tokenRequests());
    }

    private static function tokenRequests(): int
    {
        return \count(array_keys(
            FakeAtProtoNetwork::requests(),
            'POST ' . FakeAuthorizationServer::TOKEN_ENDPOINT,
            strict: true,
        ));
    }

    private static function assertSignInFailed(KernelBrowser $client): void
    {
        static::assertSame('http://localhost/login', $client->getRequest()->getUri());
        self::assertSelectorTextSame('#handle-error', text: 'The sign-in didn’t complete. Try again.');
    }
}
