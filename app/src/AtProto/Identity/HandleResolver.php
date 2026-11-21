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
    private const string DID_SYNTAX = '/^did:[a-z]+:[a-zA-Z0-9._:%-]+$/';

    public function __construct(
        private DnsTxtRecords $dns,
        #[Autowire(service: 'atproto.client')]
        private HttpClientInterface $client,
    ) {}

    /**
     * @return non-empty-string|null the DID the domain of the handle declares, if any
     */
    public function resolve(Handle $handle): ?string
    {
        return $this->resolveWithDns($handle) ?? $this->resolveWithWellKnown($handle);
    }

    /**
     * @return non-empty-string|null
     */
    private function resolveWithDns(Handle $handle): ?string
    {
        $dids = [];
        foreach ($this->dns->lookup("_atproto.{$handle}") as $record) {
            $did = str_starts_with($record, 'did=') ? substr($record, offset: 4) : '';

            if (1 === preg_match(self::DID_SYNTAX, $did)) {
                $dids[] = $did;
            }
        }

        // Several DIDs make the handle ambiguous, which the specification treats as no DID.
        return 1 === \count($dids) && '' !== $dids[0] ? $dids[0] : null;
    }

    /**
     * @return non-empty-string|null
     */
    private function resolveWithWellKnown(Handle $handle): ?string
    {
        try {
            $response = $this->client->request('GET', "https://{$handle}/.well-known/atproto-did");
            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $did = trim($response->getContent());
        } catch (ExceptionInterface) {
            // A domain without a server is a handle that names no account.
            return null;
        }

        return 1 === preg_match(self::DID_SYNTAX, $did) && '' !== $did ? $did : null;
    }
}
