<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://atproto.com/specs/handle#handle-resolution
 */
final readonly class HandleResolver
{
    /**
     * The specification allows the well-known address of a handle to redirect, as a domain that moved
     * to "www" does, for a reasonable number of hops.
     */
    private const int MAX_REDIRECTS = 3;

    public function __construct(
        private DnsTxtRecords $dns,
        #[Autowire(service: 'atproto.client')]
        private HttpClientInterface $client,
    ) {}

    /**
     * @return ?Did the DID the domain of the handle declares, if any
     */
    public function resolve(Handle $handle): ?Did
    {
        return $this->resolveWithDns($handle) ?? $this->resolveWithWellKnown($handle);
    }

    private function resolveWithDns(Handle $handle): ?Did
    {
        $dids = [];
        foreach ($this->dns->lookup("_atproto.{$handle}") as $record) {
            $did = Did::tryFrom(str_starts_with($record, 'did=') ? substr($record, offset: 4) : '');

            if (null !== $did) {
                $dids[] = $did;
            }
        }

        // Several DIDs make the handle ambiguous, which the specification treats as no DID.
        return 1 === \count($dids) ? $dids[0] : null;
    }

    private function resolveWithWellKnown(Handle $handle): ?Did
    {
        try {
            $response = $this->client->request('GET', "https://{$handle}/.well-known/atproto-did", [
                'max_redirects' => self::MAX_REDIRECTS,
            ]);
            if (200 !== $response->getStatusCode()) {
                return null;
            }

            return Did::tryFrom(trim($response->getContent()));
        } catch (ExceptionInterface) {
            // A domain without a server is a handle that names no account.
            return null;
        }
    }
}
