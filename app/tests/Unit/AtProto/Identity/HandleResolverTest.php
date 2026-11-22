<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\Identity;

use App\AtProto\Identity\Handle;
use App\AtProto\Identity\HandleResolver;
use App\Tests\Fake\FakeAtProtoNetwork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HandleResolverTest extends TestCase
{
    /**
     * A domain often moves its pages to "www": the specification allows the well-known address of a
     * handle to redirect. BoundedHttpClientTest shows that a request that allows redirections follows them.
     *
     * @see https://atproto.com/specs/handle#handle-resolution
     */
    public function testTheWellKnownAddressOfAHandleMayRedirect(): void
    {
        FakeAtProtoNetwork::reset();
        $allowed = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (
            &$allowed,
        ): MockResponse {
            $allowed = \is_int($options['max_redirects'] ?? null) ? $options['max_redirects'] : null;

            return new MockResponse('did:plc:redirected34567abcdefghij');
        });

        $did = new HandleResolver(new FakeAtProtoNetwork(), $client)->resolve(Handle::fromString('moved.fake.test'));

        static::assertSame('did:plc:redirected34567abcdefghij', $did?->value);
        static::assertGreaterThan(0, $allowed);
    }
}
