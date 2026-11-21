<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

use App\AtProto\Identity\Exception\AccountNotFound;
use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\JsonObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the handle and the PDS that a DID document declares.
 *
 * @see https://atproto.com/specs/did
 */
final readonly class DidResolver
{
    private const string PLC_DIRECTORY = 'https://plc.directory';

    public function __construct(
        #[Autowire(service: 'atproto.client')]
        private HttpClientInterface $client,
    ) {}

    /**
     * @throws AccountNotFound     when the DID has no valid document
     * @throws ProviderUnreachable when the server of the document does not answer
     */
    public function resolve(string $did): DidDocument
    {
        return DidDocument::fromJson($did, $this->fetch(self::documentUrl($did)));
    }

    private static function documentUrl(string $did): string
    {
        if (1 === preg_match('/^did:plc:[a-z2-7]{24}$/', $did)) {
            return self::PLC_DIRECTORY . '/' . $did;
        }

        // AT Protocol only allows did:web on a whole hostname, without a path or a port.
        if (1 === preg_match('/^did:web:[a-z0-9.-]+$/', $did)) {
            return 'https://' . substr($did, \strlen('did:web:')) . '/.well-known/did.json';
        }

        throw new AccountNotFound(\sprintf('"%s" is not a supported DID.', $did));
    }

    private function fetch(string $url): JsonObject
    {
        try {
            $response = $this->client->request('GET', $url);
            $status = $response->getStatusCode();

            if (404 === $status || 410 === $status) {
                throw new AccountNotFound(\sprintf('No DID document at "%s".', $url));
            }

            if (200 !== $status) {
                throw new ProviderUnreachable(\sprintf('"%s" answered %d.', $url, $status));
            }

            return new JsonObject($response->toArray());
        } catch (TransportExceptionInterface $exception) {
            throw new ProviderUnreachable(\sprintf('"%s" does not answer.', $url), previous: $exception);
        } catch (ExceptionInterface $exception) {
            throw new AccountNotFound(\sprintf('"%s" is not a DID document.', $url), previous: $exception);
        }
    }
}
