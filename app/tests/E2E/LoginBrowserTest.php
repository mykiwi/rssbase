<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeProviderRoute;
use Playwright\Page\PageInterface;
use Playwright\Symfony\Test\PlaywrightTestCase;

use function Playwright\Testing\expect;

final class LoginBrowserTest extends PlaywrightTestCase
{
    /** Browsers refuse a cookie for "localhost": the languages share a session on a domain of two labels. */
    private const string SITE = 'http://rssbase.localhost';
    private const string FRENCH_SITE = 'http://fr.rssbase.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        FakeAtProtoNetwork::reset();
        // 127.0.0.1 receives the callback of the loopback client.
        static::getPlaywrightClient()->setInterceptedHosts(['rssbase.localhost', 'fr.rssbase.localhost', '127.0.0.1']);
    }

    public function testAVisitorSignsInStaysSignedInOnBothLanguagesAndSignsOut(): void
    {
        $page = $this->getPage();
        $page->context()->route('https://*.fake.test/**', new FakeProviderRoute());

        $page->goto(self::SITE . '/');
        $page->getByRole('link', ['name' => 'Start'])->click();
        $page->getByLabel('Handle')->fill(FakeAtProtoNetwork::ALICE);
        $page->getByRole('button', ['name' => 'Continue'])->click();

        $page->waitForURL('https://auth.fake.test/oauth/authorize**');
        $page->getByRole('link', ['name' => 'Approve'])->click();

        $page->waitForURL(self::SITE . '/feeds');
        expect($page->locator('h1'))->toHaveText('My feeds');
        expect($page->locator('[data-test="account-handle"]'))->toHaveText('@' . FakeAtProtoNetwork::ALICE);
        self::assertTheAvatarIsShown($page);

        // The French site shares the session.
        $page->goto(self::FRENCH_SITE . '/feeds');
        expect($page->locator('h1'))->toHaveText('Mes flux');

        // A browser that closes forgets its session cookies, and keeps the others.
        $persistent = array_values(array_filter(
            $page->context()->cookies(),
            static fn(array $cookie): bool => $cookie['expires'] > 0,
        ));
        $page->context()->clearCookies();
        $page->context()->addCookies($persistent);
        $page->goto(self::SITE . '/feeds');
        expect($page->locator('h1'))->toHaveText('My feeds');

        $page->getByRole('button', ['name' => 'Sign out'])->click();
        $page->waitForURL(self::SITE . '/');
        $page->goto(self::SITE . '/feeds');
        static::assertSame(self::SITE . '/login', $page->url());

        self::assertNoBrowserError($page);
    }

    private static function assertTheAvatarIsShown(PageInterface $page): void
    {
        $avatar = $page->locator('[data-test="account-avatar"]');
        expect($avatar)->toBeVisible();
        $page->waitForFunction('() => document.querySelector(\'[data-test="account-avatar"]\').complete');

        // The PDS serves it as an attachment, with a sandbox policy: the browser shows it all the same.
        static::assertSame(1, $avatar->evaluate('image => image.naturalWidth'));
    }

    private static function assertNoBrowserError(PageInterface $page): void
    {
        $consoleErrors = array_filter(
            $page->consoleMessages(),
            static fn($message): bool => 'error' === $message->type(),
        );
        static::assertSame([], array_map(static fn($message): string => $message->text(), $consoleErrors));
        static::assertSame([], $page->pageErrors());
    }
}
