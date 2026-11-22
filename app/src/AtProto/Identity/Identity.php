<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

/**
 * An account whose DID document confirms its handle, with the PDS that hosts its data.
 */
final readonly class Identity
{
    public function __construct(
        public Did $did,
        public Handle $handle,
        public Pds $pds,
    ) {}
}
