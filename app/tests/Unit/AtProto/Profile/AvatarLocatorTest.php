<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\Profile;

use App\AtProto\Identity\Handle;
use App\AtProto\Identity\Identity;
use App\AtProto\Profile\AvatarLocator;
use App\Tests\Fake\FakeAtProtoNetwork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class AvatarLocatorTest extends TestCase
{
    private const string PDS = 'https://pds.fake.test';
    private const string RECORD =
        self::PDS
            . '/xrpc/com.atproto.repo.getRecord?repo=did:plc:carol234567abcdefghijklm&collection=app.bsky.actor.profile&rkey=self';

    private AvatarLocator $locator;
    private Identity $carol;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        $this->locator = new AvatarLocator(new MockHttpClient(new FakeAtProtoNetwork()));
        $this->carol = new Identity(
            'did:plc:carol234567abcdefghijklm',
            Handle::fromString('carol.fake.test'),
            self::PDS,
        );
    }

    public function testAProfileWithoutAvatarHasNoImage(): void
    {
        FakeAtProtoNetwork::publish(self::RECORD, ['value' => [
            '$type' => 'app.bsky.actor.profile',
            'displayName' => 'Carol',
        ]]);

        static::assertNull($this->locator->locate($this->carol));
    }

    public function testAnAvatarReferenceThatIsNotACidHasNoImage(): void
    {
        FakeAtProtoNetwork::publish(self::RECORD, ['value' => ['avatar' => ['ref' => ['$link' => '../../evil?x=']]]]);

        static::assertNull($this->locator->locate($this->carol));
    }

    public function testAnUnreachablePdsHasNoImage(): void
    {
        FakeAtProtoNetwork::makeUnreachable('pds.fake.test');

        static::assertNull($this->locator->locate($this->carol));
    }
}
