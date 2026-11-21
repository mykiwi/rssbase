<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtProto\Identity;

use App\AtProto\Identity\Did;
use App\AtProto\Identity\DidResolver;
use App\AtProto\Identity\Exception\AccountNotFound;
use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\Identity\Handle;
use App\AtProto\Identity\HandleResolver;
use App\AtProto\Identity\IdentityResolver;
use App\Tests\Fake\FakeAtProtoNetwork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IdentityResolverTest extends TestCase
{
    /** Its domain points to it, and its document claims the handle of Alice. */
    private const string IMPOSTOR_DID = 'did:plc:impostor234567abcdefghij';

    private IdentityResolver $resolver;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        $this->resolver = self::resolverWith(new MockHttpClient(new FakeAtProtoNetwork()));
    }

    public function testAHandleResolvesThroughItsDnsRecord(): void
    {
        $identity = $this->resolver->resolveHandle(Handle::fromInput(FakeAtProtoNetwork::ALICE));

        static::assertSame(FakeAtProtoNetwork::ALICE_DID, $identity->did->value);
        static::assertSame(FakeAtProtoNetwork::ALICE, $identity->handle->value);
        static::assertSame(FakeAtProtoNetwork::PDS, $identity->pds->value);
        static::assertNotContains(
            'GET https://alice.fake.test/.well-known/atproto-did',
            FakeAtProtoNetwork::requests(),
        );
    }

    public function testAHandleWithoutDnsRecordResolvesThroughItsWellKnownFile(): void
    {
        $identity = $this->resolver->resolveHandle(Handle::fromInput('bob'));

        static::assertSame(FakeAtProtoNetwork::BOB_DID, $identity->did->value);
        static::assertSame(FakeAtProtoNetwork::BOB, $identity->handle->value);
    }

    public function testADidWebAccountResolves(): void
    {
        $identity = $this->resolver->resolveHandle(Handle::fromInput(FakeAtProtoNetwork::CAROL));

        static::assertSame(FakeAtProtoNetwork::CAROL_DID, $identity->did->value);
        static::assertContains('GET https://carol.fake.test/.well-known/did.json', FakeAtProtoNetwork::requests());
    }

    public function testAHandleThatResolvesToNoDidNamesNoAccount(): void
    {
        $this->expectException(AccountNotFound::class);

        $this->resolver->resolveHandle(Handle::fromInput('nobody.fake.test'));
    }

    public function testAHandleThatTheDidDocumentDoesNotConfirmNamesNoAccount(): void
    {
        FakeAtProtoNetwork::addAccount(
            handle: 'impostor.fake.test',
            did: self::IMPOSTOR_DID,
            declaredHandle: FakeAtProtoNetwork::ALICE,
        );

        $this->expectException(AccountNotFound::class);

        $this->resolver->resolveHandle(Handle::fromInput('impostor.fake.test'));
    }

    public function testAnUnreachableDidDirectoryIsAnUnreachableProvider(): void
    {
        FakeAtProtoNetwork::makeUnreachable('plc.directory');

        $this->expectException(ProviderUnreachable::class);

        $this->resolver->resolveHandle(Handle::fromInput(FakeAtProtoNetwork::ALICE));
    }

    public function testADidResolvesToItsHandleOnlyWhenTheHandlePointsBack(): void
    {
        FakeAtProtoNetwork::addAccount(
            handle: 'impostor.fake.test',
            did: self::IMPOSTOR_DID,
            declaredHandle: FakeAtProtoNetwork::ALICE,
        );

        static::assertSame(
            FakeAtProtoNetwork::ALICE,
            $this->resolver->resolveDid(Did::fromString(FakeAtProtoNetwork::ALICE_DID))->handle->value,
        );
        static::assertSame(
            'handle.invalid',
            $this->resolver->resolveDid(Did::fromString(self::IMPOSTOR_DID))->handle->value,
        );
    }

    public function testAHandleOnAPrivateAddressIsNeverCalled(): void
    {
        // The test gives the address that DNS would return, so that the client resolves nothing itself.
        $client = new NoPrivateNetworkHttpClient(new MockHttpClient(
            new FakeAtProtoNetwork(),
        ))->withOptions(['resolve' => ['intranet.fake.test' => '10.0.0.7']]);

        try {
            self::resolverWith($client)->resolveHandle(Handle::fromInput('intranet.fake.test'));
            static::fail('A handle on a private address resolved.');
        } catch (AccountNotFound) {
            static::assertSame([], FakeAtProtoNetwork::requests());
        }
    }

    private static function resolverWith(HttpClientInterface $client): IdentityResolver
    {
        return new IdentityResolver(new HandleResolver(new FakeAtProtoNetwork(), $client), new DidResolver($client));
    }
}
