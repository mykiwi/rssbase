<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;

use function Playwright\Testing\expect;

final class HomePageBrowserTest extends PlaywrightTestCase
{
    public function testTheHomePageIsStyledWithoutBrowserErrors(): void
    {
        $page = $this->visit('/');

        $this->assertResponseStatusCode(200);
        // The title breaks into lines on a wide screen: the comparison ignores them.
        static::assertSame(
            'Open Source RSS Reader for an open internet using Atmosphere',
            $page->locator('h1')->evaluate('title => title.textContent.replace(/\\s+/g, " ").trim()'),
        );
        $this->assertSelectorVisible('h1');

        // Tailwind fills the start button: an unstyled link has no background.
        static::assertNotSame(
            'rgba(0, 0, 0, 0)',
            $page->locator('main a[href="/login"]')->evaluate('button => getComputedStyle(button).backgroundColor'),
        );

        // The pixel field paints squares once its controller is connected.
        $page->waitForFunction(<<<'JS'
            () => {
                const canvas = document.querySelector('canvas');
                const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;

                return pixels.some((value, index) => 3 === index % 4 && value > 0);
            }
            JS);

        $consoleErrors = array_filter(
            $page->consoleMessages(),
            static fn($message): bool => 'error' === $message->type(),
        );
        static::assertSame([], array_map(static fn($message): string => $message->text(), $consoleErrors));
        static::assertSame([], $page->pageErrors());
    }

    public function testTheLanguageMenuFindsALanguageAndOpensIt(): void
    {
        // The French site is a subdomain, which the kernel has to serve as well.
        static::getPlaywrightClient()->setInterceptedHosts(['localhost', 'fr.localhost']);
        $page = $this->visit('/');
        $menu = $page->locator('#language-menu');
        expect($menu)->toBeHidden();

        $page->locator('[aria-controls="language-menu"]')->click();
        expect($menu)->toBeVisible();

        $search = $menu->locator('input');
        $search->fill('deutsch');
        expect($menu->locator('li a'))->toBeHidden();

        // "French" is the English name of the language, and the search ignores the case.
        $search->fill('FRENCH');
        expect($menu->locator('li a'))->toBeVisible();
        $search->press('Enter');

        $page->waitForURL('http://fr.localhost/');
        static::assertSame('http://fr.localhost/', $page->url());
        expect($page->locator('#language-menu [aria-current="true"]'))->toHaveText('Français');
    }
}
