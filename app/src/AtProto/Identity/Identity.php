<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

/**
 * An account whose DID document confirms its handle, with the PDS that hosts its data.
 */
final readonly class Identity
{
    /**
     * @param non-empty-string $did
     */
    public function __construct(
        public string $did,
        public Handle $handle,
        public string $pds,
    ) {}
}
