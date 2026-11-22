<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto;

use App\AtProto\BoundedHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
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

    /**
     * Behind NoPrivateNetworkHttpClient, as in production: that client replaces the options of the
     * configuration with its own defaults, and follows redirects itself.
     */
    public function testTheLimitsOfARequestReachTheTransportBehindThePrivateNetworkGuard(): void
    {
        $timeout = null;
        $maxDuration = null;
        $transport = new MockHttpClient(static function (string $method, string $url, array $options) use (
            &$timeout,
            &$maxDuration,
        ): MockResponse {
            $timeout = \is_float($options['timeout'] ?? null) ? $options['timeout'] : null;
            $maxDuration = \is_float($options['max_duration'] ?? null) ? $options['max_duration'] : null;

            return new MockResponse();
        });

        new BoundedHttpClient(new NoPrivateNetworkHttpClient($transport))
            ->request('GET', url: 'https://1.1.1.1/')
            ->getStatusCode();

        static::assertSame(5.0, $timeout);
        static::assertSame(10.0, $maxDuration);
    }
}
