<?php

declare(strict_types=1);

namespace App\AtProto;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Stops reading an answer that is larger than any document of the network: the addresses come from what
 * visitors type, and the client limits the time of a request, not its size.
 */
final readonly class BoundedHttpClient implements HttpClientInterface
{
    /**
     * DID documents, server metadata, profile records and token answers are a few kilobytes.
     */
    private const int MAX_BYTES = 256 * 1024;

    public function __construct(
        private HttpClientInterface $client,
    ) {}

    #[\Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $options['on_progress'] = static function (int $downloaded, int $expected): void {
            if (max($downloaded, $expected) > self::MAX_BYTES) {
                throw new TransportException(\sprintf('The answer is larger than %d bytes.', self::MAX_BYTES));
            }
        };

        return $this->client->request($method, $url, $options);
    }

    #[\Override]
    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    #[\Override]
    public function withOptions(array $options): static
    {
        return new self($this->client->withOptions($options));
    }
}
