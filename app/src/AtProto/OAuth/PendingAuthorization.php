<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\Identity\Identity;

/**
 * An authorization request sent to a server, kept in the session until the visitor comes back.
 */
final readonly class PendingAuthorization
{
    /**
     * @param ?Identity $identity the account the visitor typed the handle of, or null when they start
     *                            from a provider, where they may create the account
     */
    private function __construct(
        public string $state,
        public AuthorizationServer $server,
        public ?Identity $identity,
        #[\SensitiveParameter]
        public string $codeVerifier,
        public DpopKey $dpopKey,
    ) {}

    public static function start(string $state, AuthorizationServer $server, ?Identity $identity): self
    {
        return new self($state, $server, $identity, Base64Url::encode(random_bytes(32)), DpopKey::generate());
    }

    public function withDpopKey(DpopKey $dpopKey): self
    {
        return new self($this->state, $this->server, $this->identity, $this->codeVerifier, $dpopKey);
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc7636#section-4.2
     */
    public function codeChallenge(): string
    {
        return Base64Url::encode(hash('sha256', $this->codeVerifier, binary: true));
    }

    /**
     * Keeps the secrets out of dumps, the profiler included.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['issuer' => $this->server->issuer, 'did' => $this->identity?->did];
    }
}
