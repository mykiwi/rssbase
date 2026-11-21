<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

use App\AtProto\Identity\Exception\AccountNotFound;
use App\AtProto\Identity\Exception\ProviderUnreachable;

/**
 * Turns a handle or a DID into an identity whose handle and DID confirm each other.
 *
 * @see https://atproto.com/specs/handle#handle-resolution
 */
final readonly class IdentityResolver
{
    public function __construct(
        private HandleResolver $handleResolver,
        private DidResolver $didResolver,
    ) {}

    /**
     * @throws AccountNotFound
     * @throws ProviderUnreachable
     */
    public function resolveHandle(Handle $handle): Identity
    {
        $did = $this->handleResolver->resolve($handle) ?? throw new AccountNotFound(\sprintf(
            '"%s" resolves to no DID.',
            $handle,
        ));
        $document = $this->didResolver->resolve($did);

        if (null === $document->handle || strtolower($document->handle) !== $handle->value) {
            throw new AccountNotFound(\sprintf(
                'The document of "%s" does not confirm the handle "%s".',
                $did,
                $handle,
            ));
        }

        return new Identity($did, $handle, $document->pds);
    }

    /**
     * @param non-empty-string $did
     *
     * @throws AccountNotFound
     * @throws ProviderUnreachable
     */
    public function resolveDid(string $did): Identity
    {
        $document = $this->didResolver->resolve($did);

        return new Identity($did, $this->confirmedHandle($did, $document->handle), $document->pds);
    }

    private function confirmedHandle(string $did, ?string $declared): Handle
    {
        try {
            $handle = Handle::fromString($declared ?? '');
        } catch (AccountNotFound) {
            return Handle::invalid();
        }

        return $this->handleResolver->resolve($handle) === $did ? $handle : Handle::invalid();
    }
}
