<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\OAuth;

use App\AtProto\OAuth\SignedState;
use PHPUnit\Framework\TestCase;

final class SignedStateTest extends TestCase
{
    public function testAStateGivesBackTheSiteItWasIssuedFor(): void
    {
        $signedState = new SignedState('secret');
        $state = $signedState->issue('http://fr.rssbase.localhost:8000');

        static::assertSame('http://fr.rssbase.localhost:8000', $signedState->site($state));
        static::assertNotSame($state, $signedState->issue('http://fr.rssbase.localhost:8000'));
    }

    public function testAStateIssuedWithAnotherSecretIsRefused(): void
    {
        $state = new SignedState('another secret')->issue('https://rssbase.example');

        static::assertNull(new SignedState('secret')->site($state));
    }

    public function testAStateWhoseSiteWasReplacedIsRefused(): void
    {
        $signedState = new SignedState('secret');
        [, $signature] = explode('.', $signedState->issue('https://rssbase.example'), limit: 2);
        [$foreignPayload] = explode('.', $signedState->issue('https://evil.example'), limit: 2);

        static::assertNull($signedState->site("{$foreignPayload}.{$signature}"));
    }

    public function testASiteThatIsNotAnOriginIsRefused(): void
    {
        $signedState = new SignedState('secret');

        static::assertNull($signedState->site($signedState->issue(
            'https://rssbase.example/path?next=https://evil.example',
        )));
        static::assertNull($signedState->site('forged'));
    }
}
