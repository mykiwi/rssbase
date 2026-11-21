<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto;

use App\AtProto\BoundedHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class BoundedHttpClientTest extends TestCase
{
    private const int LIMIT = 256 * 1024;

    public function testADocumentOfTheNetworkIsRead(): void
    {
        $client = new BoundedHttpClient(new MockHttpClient(new MockResponse(str_repeat('a', self::LIMIT))));

        static::assertSame(self::LIMIT, \strlen($client->request('GET', url: 'https://pds.fake.test/')->getContent()));
    }

    public function testAnAnswerLargerThanAnyDocumentIsNotRead(): void
    {
        $client = new BoundedHttpClient(new MockHttpClient(new MockResponse(str_repeat('a', self::LIMIT + 1))));

        $this->expectException(TransportExceptionInterface::class);

        $client->request('GET', url: 'https://pds.fake.test/')->getContent();
    }
}
