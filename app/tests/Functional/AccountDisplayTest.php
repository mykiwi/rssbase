<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Fake\FakeAtProtoNetwork;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountDisplayTest extends WebTestCase
{
    use SignsInThroughTheFakeProvider;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
    }

    public function testMyFeedsShowsTheAvatarFromThePdsTheHandleAndTheSignOut(): void
    {
        $client = static::createClient();
        self::answer($client, self::submitHandle($client, FakeAtProtoNetwork::ALICE), FakeAtProtoNetwork::ALICE_DID);

        self::assertSelectorTextSame('[data-test="account-handle"]', text: '@' . FakeAtProtoNetwork::ALICE);
        self::assertSelectorExists('[data-test="account"] button[type="submit"]:contains("Sign out")');
        static::assertSame(
            FakeAtProtoNetwork::PDS
            . '/xrpc/com.atproto.sync.getBlob?did='
            . urlencode(FakeAtProtoNetwork::ALICE_DID)
            . '&cid='
            . FakeAtProtoNetwork::ALICE_AVATAR_CID,
            $client->getCrawler()->filter('[data-test="account-avatar"]')->attr('src'),
        );
    }

    public function testAnAccountWithoutProfileShowsANeutralImage(): void
    {
        $client = static::createClient();
        self::answer($client, self::submitHandle($client, FakeAtProtoNetwork::BOB), FakeAtProtoNetwork::BOB_DID);

        self::assertSelectorExists('[data-test="account-avatar-neutral"]');
        self::assertSelectorNotExists('[data-test="account-avatar"]');
    }
}
