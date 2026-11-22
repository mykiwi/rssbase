<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto;

use App\AtProto\HttpsUrl;
use PHPUnit\Framework\TestCase;

final class HttpsUrlTest extends TestCase
{
    public function testAQueryIsAddedToAnAddressWithoutOne(): void
    {
        static::assertSame(
            'https://auth.example/authorize?client_id=c',
            HttpsUrl::fromString('https://auth.example/authorize')->withQuery('client_id=c')->value,
        );
    }

    /**
     * An authorization endpoint may carry a query, which the client must keep.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc6749#section-3.1
     */
    public function testAQueryIsAddedToTheOneAnAddressAlreadyHas(): void
    {
        static::assertSame(
            'https://auth.example/authorize?tenant=t&client_id=c',
            HttpsUrl::fromString('https://auth.example/authorize?tenant=t')->withQuery('client_id=c')->value,
        );
    }
}
