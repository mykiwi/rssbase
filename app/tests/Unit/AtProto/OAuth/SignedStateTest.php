<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\OAuth;

use App\AtProto\OAuth\SignedState;
use App\AtProto\OAuth\Site;
use App\AtProto\OAuth\State;
use PHPUnit\Framework\TestCase;

final class SignedStateTest extends TestCase
{
    public function testAStateGivesBackTheSiteItWasIssuedFor(): void
    {
        $signedState = new SignedState('secret');
        $site = self::site('http://fr.rssbase.localhost:8000');
        $state = $signedState->issue($site);

        static::assertSame('http://fr.rssbase.localhost:8000', $signedState->site($state)?->value);
        static::assertFalse($state->equals($signedState->issue($site)));
    }

    public function testAStateIssuedWithAnotherSecretIsRefused(): void
    {
        $state = new SignedState('another secret')->issue(self::site('https://rssbase.example'));

        static::assertNull(new SignedState('secret')->site($state));
    }

    public function testAStateWhoseSiteWasReplacedIsRefused(): void
    {
        $signedState = new SignedState('secret');
        [, $signature] = explode('.', $signedState->issue(self::site('https://rssbase.example'))->value, limit: 2);
        [$foreignPayload] = explode('.', $signedState->issue(self::site('https://evil.example'))->value, limit: 2);

        static::assertNull($signedState->site(new State("{$foreignPayload}.{$signature}")));
    }

    public function testASiteThatIsNotAnOriginIsRefused(): void
    {
        $signedState = new SignedState('secret');

        static::assertNull(Site::tryFrom('https://rssbase.example/path?next=https://evil.example'));
        static::assertNull($signedState->site(new State('forged')));
    }

    private static function site(string $value): Site
    {
        return Site::tryFrom($value) ?? throw new \InvalidArgumentException("\"{$value}\" is not a site.");
    }
}
