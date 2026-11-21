<?php

declare(strict_types=1);

namespace App\AtProto\Provider;

use App\AtProto\OAuth\Issuer;

/**
 * A provider where a visitor without an account can create one, chosen by RssBase.
 */
final readonly class Provider
{
    /**
     * @param string $id the name of the provider in the address of its form
     */
    public function __construct(
        public string $id,
        public string $name,
        public Issuer $issuer,
        public Region $region,
        public ?ProviderLabel $label = null,
    ) {}
}
