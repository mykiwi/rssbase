<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

/**
 * An access token and the single-use refresh token that replaces both.
 */
final readonly class TokenPair
{
    /**
     * Refreshed this long before it expires, so that a token sent to a server does not expire on the way.
     */
    private const int LEEWAY = 30;

    /**
     * @param int $expiresAt when the access token expires, as a Unix timestamp
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $access,
        #[\SensitiveParameter]
        public string $refresh,
        public int $expiresAt,
    ) {}

    public function isExpired(int $now): bool
    {
        return $now >= ($this->expiresAt - self::LEEWAY);
    }

    /**
     * Keeps the tokens out of dumps, the profiler included.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['expiresAt' => $this->expiresAt];
    }
}
