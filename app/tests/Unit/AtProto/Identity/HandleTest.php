<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\Identity;

use App\AtProto\Identity\Exception\AccountNotFound;
use App\AtProto\Identity\Handle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class HandleTest extends TestCase
{
    #[TestWith(['nom.bsky.social', 'nom.bsky.social'])]
    #[TestWith(['  @Nom.Bsky.Social  ', 'nom.bsky.social'])]
    #[TestWith(['romain', 'romain.bsky.social'])]
    #[TestWith(['@Romain ', 'romain.bsky.social'])]
    #[TestWith(['xn--caf-dma.example.org', 'xn--caf-dma.example.org'])]
    #[TestWith(['alice.test', 'alice.test'])]
    public function testTheInputIsNormalized(string $input, string $handle): void
    {
        static::assertSame($handle, Handle::fromInput($input)->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'empty' => [''];
        yield 'only an at sign' => ['@'];
        yield 'a space inside' => ['jo hn.bsky.social'];
        yield 'a numeric top-level domain' => ['john.123'];
        yield 'a label starting with a hyphen' => ['-john.bsky.social'];
        yield 'a disallowed top-level domain' => ['john.local'];
        yield 'longer than 253 characters' => [str_repeat('abcdefghi.', times: 26) . 'com'];
        yield 'a URL' => ['https://bsky.app/profile/john.bsky.social'];
    }

    #[DataProvider('invalidInputs')]
    public function testAnInvalidInputNamesNoAccount(string $input): void
    {
        $this->expectException(AccountNotFound::class);

        Handle::fromInput($input);
    }
}
