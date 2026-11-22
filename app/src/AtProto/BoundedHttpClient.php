<?php

declare(strict_types=1);

namespace App\AtProto;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Limits what a request to the network may cost: its time, its redirections and the size of its answer.
 * The addresses come from what visitors type.
 *
 * The limits are set on each request, not in the configuration of the client: NoPrivateNetworkHttpClient
 * replaces the configured options with its own defaults, and follows redirections itself.
 */
final readonly class BoundedHttpClient implements HttpClientInterface
{
    /**
     * DID documents, server metadata, profile records and token answers are a few kilobytes.
     */
    private const int MAX_BYTES = 256 * 1024;

    /**
     * Seconds without receiving anything, then seconds for the whole request.
     */
    private const float IDLE_TIMEOUT = 5.0;
    private const float MAX_DURATION = 10.0;

    public function __construct(
        private HttpClientInterface $client,
    ) {}

    #[\Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        // The documents of the network are read where they are, unless the specification of one allows
        // it to move: a request then says how far it follows.
        $options['max_redirects'] ??= 0;
        $options['timeout'] = self::IDLE_TIMEOUT;
        $options['max_duration'] = self::MAX_DURATION;
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
