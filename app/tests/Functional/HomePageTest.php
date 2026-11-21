<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomePageTest extends WebTestCase
{
    public function testHomePagePresentsTheReader(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', text: 'Open Source RSS Reader for an open internet using AT Protocol');
        self::assertSelectorTextSame('header nav > a[href="https://github.com/mykiwi/rssbase"]', text: 'Source code');
        self::assertSelectorTextSame('header nav > a:first-of-type', text: 'Changelog');
        self::assertSelectorTextSame('header nav > a:last-of-type', text: 'Social media');
        self::assertSelectorTextSame('main a', text: 'Start');
    }

    public function testTheFrenchSubdomainServesTheFrenchPage(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: 'http://localhost/');
        self::assertSelectorTextContains('header nav [aria-current="true"]', text: 'English');

        $client->clickLink('Français');

        self::assertResponseIsSuccessful();
        static::assertSame('http://fr.localhost/', $client->getRequest()->getUri());
        self::assertSelectorExists('html[lang="fr"]');
        self::assertSelectorTextSame('h1', text: 'Lecteur RSS Open Source pour un internet ouvert avec AT Protocol');
        self::assertSelectorTextContains('header nav [aria-current="true"]', text: 'Français');
        self::assertSelectorTextSame('main a', text: 'Commencer');

        $client->clickLink('English');

        static::assertSame('http://localhost/', $client->getRequest()->getUri());
        self::assertSelectorExists('html[lang="en"]');
    }

    public function testTheLanguageLinksFollowTheDomainOfTheSite(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: 'https://fr.rssbase.example/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="fr"]');

        $client->clickLink('English');

        static::assertSame('https://rssbase.example/', $client->getRequest()->getUri());
    }

    public function testUnknownUrlIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }
}
