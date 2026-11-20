<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomePageTest extends WebTestCase
{
    public function testHomePageSaysHelloWorldInACard(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-slot="card"] [data-slot="card-title"] h1', text: 'Hello world');
    }

    public function testUnknownUrlIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', uri: '/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }
}
