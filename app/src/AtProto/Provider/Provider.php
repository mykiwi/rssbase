<?php

declare(strict_types=1);

namespace App\AtProto\Provider;

/**
 * A provider where a visitor without an account can create one, chosen by RssBase.
 */
final readonly class Provider
{
    /**
     * @param 'eu'|'us'                $region where the provider hosts the data of its accounts
     * @param 'recommended'|'popular'|null $label
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $issuer,
        public string $region,
        public ?string $label = null,
    ) {}
}
