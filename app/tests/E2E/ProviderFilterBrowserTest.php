<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Playwright\Symfony\Test\PlaywrightTestCase;

use function Playwright\Testing\expect;

final class ProviderFilterBrowserTest extends PlaywrightTestCase
{
    private const string FRENCH_SITE = 'http://fr.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        static::getPlaywrightClient()->setInterceptedHosts(['localhost', 'fr.localhost']);
    }

    /**
     * The page holds every provider: the filter shows a region without asking the server, and the address keeps
     * it for a link, a reload or the Back button.
     */
    public function testTheFilterShowsARegionWithoutAskingTheServer(): void
    {
        $page = $this->getPage();
        $page->goto(self::FRENCH_SITE . '/providers');
        $filter = $page->locator('[data-test="provider-filter"]');
        $shown = $page->locator('[data-test="providers"] li:not([hidden]) [data-test="provider-name"]');
        expect($shown)->toHaveCount(4);
        // A page loaded again would lose this mark, and a request to the server would add a resource entry.
        $before = (int) $page->evaluate(
            '() => { window.stillTheSamePage = true; return performance.getEntriesByType("resource").length; }',
        );

        $filter->getByRole('link', ['name' => 'UE', 'exact' => true])->click();

        expect($shown)->toHaveCount(2);
        expect($shown->first())->toHaveText('Eurosky');
        expect($filter->locator('[aria-current="page"]'))->toHaveText('UE');
        expect($page)->toHaveURL(self::FRENCH_SITE . '/providers?region=eu');

        $page->goBack();

        expect($shown)->toHaveCount(4);
        expect($filter->locator('[aria-current="page"]'))->toHaveText('Tous');
        expect($page)->toHaveURL(self::FRENCH_SITE . '/providers');
        static::assertSame(
            [true, $before],
            $page->evaluate(
                '() => [window.stillTheSamePage === true, performance.getEntriesByType("resource").length]',
            ),
        );
    }
}
