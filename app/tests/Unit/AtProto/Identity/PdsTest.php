<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\Identity;

use App\AtProto\Identity\Pds;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PdsTest extends TestCase
{
    public function testAPdsIsNamedByItsHostWithoutATrailingSlash(): void
    {
        static::assertSame('https://pds.example', Pds::tryFrom('https://pds.example/')?->value);
        static::assertSame('https://pds.example:8443', Pds::tryFrom('https://pds.example:8443')?->value);
    }

    #[TestWith(['http://pds.example'])]
    #[TestWith(['https://pds.example/xrpc'])]
    #[TestWith(['https://pds.example?next=https://evil.example'])]
    #[TestWith(['https://user@pds.example'])]
    #[TestWith(['pds.example'])]
    public function testAnAddressThatIsNotAnHttpsHostIsNoPds(string $endpoint): void
    {
        static::assertNull(Pds::tryFrom($endpoint));
    }
}
