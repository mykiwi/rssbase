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

        if (true !== $document->handle?->equals($handle)) {
            throw new AccountNotFound(\sprintf(
                'The document of "%s" does not confirm the handle "%s".',
                $did,
                $handle,
            ));
        }

        return new Identity($did, $handle, $document->pds);
    }

    /**
     * @throws AccountNotFound
     * @throws ProviderUnreachable
     */
    public function resolveDid(Did $did): Identity
    {
        $document = $this->didResolver->resolve($did);

        return new Identity($did, $this->confirmedHandle($did, $document->handle), $document->pds);
    }

    /**
     * The handle a document claims counts only when the domain of the handle names the same DID.
     */
    private function confirmedHandle(Did $did, ?Handle $declared): Handle
    {
        if (null === $declared) {
            return Handle::invalid();
        }

        return true === $this->handleResolver->resolve($declared)?->equals($did) ? $declared : Handle::invalid();
    }
}
