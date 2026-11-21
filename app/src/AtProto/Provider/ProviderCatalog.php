<?php

declare(strict_types=1);

namespace App\AtProto\Provider;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ProviderCatalog
{
    /** @var list<Provider> */
    private array $providers;

    /**
     * @param list<array{id: string, name: string, issuer: string, region: 'eu'|'us', label?: 'recommended'|'popular'}> $providers
     */
    public function __construct(#[Autowire(param: 'app.atproto.providers')] array $providers)
    {
        $this->providers = array_map(static fn(array $provider): Provider => new Provider(...$provider), $providers);
    }

    /**
     * @return list<Provider> in the order of the catalog, which is the order of the screen
     */
    public function inRegion(?string $region): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn(Provider $provider): bool => null === $region || $provider->region === $region,
        ));
    }

    public function find(string $id): ?Provider
    {
        return array_find($this->providers, static fn(Provider $provider): bool => $provider->id === $id);
    }
}
