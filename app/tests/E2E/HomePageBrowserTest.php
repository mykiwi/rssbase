<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;

use function Playwright\Testing\expect;

final class HomePageBrowserTest extends PlaywrightTestCase
{
    public function testTheHomePageShowsAStyledCardWithoutBrowserErrors(): void
    {
        $page = $this->visit('/');

        $this->assertResponseStatusCode(200);
        expect($page->locator('h1'))->toHaveText('Hello world');
        $this->assertSelectorVisible('h1');

        // Tailwind rounds the corners of the card: an unstyled <div> has none.
        static::assertNotSame(
            '0px',
            $page->locator('[data-slot="card"]')->evaluate('card => getComputedStyle(card).borderTopLeftRadius'),
        );

        $consoleErrors = array_filter(
            $page->consoleMessages(),
            static fn($message): bool => 'error' === $message->type(),
        );
        static::assertSame([], array_map(static fn($message): string => $message->text(), $consoleErrors));
        static::assertSame([], $page->pageErrors());
    }
}
