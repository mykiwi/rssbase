<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\HttpsUrl;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Symfony\Component\Security\Http\OAuth2\Dpop\DpopProofFactory;

/**
 * The DPoP key of one session, which the tokens are bound to, and the last nonce the authorization
 * server gave.
 *
 * @see https://atproto.com/specs/oauth#dpop
 */
final readonly class DpopKey
{
    private function __construct(
        #[\SensitiveParameter]
        private string $jwk,
        public ?string $nonce,
    ) {}

    public static function generate(): self
    {
        return new self(json_encode(JWKFactory::createECKey('P-256'), \JSON_THROW_ON_ERROR), nonce: null);
    }

    public function withNonce(string $nonce): self
    {
        return new self($this->jwk, $nonce);
    }

    /**
     * Signs a proof for one request: each proof has its own "jti".
     */
    public function proof(string $method, HttpsUrl $url): string
    {
        return new DpopProofFactory(JWK::createFromJson($this->jwk))->createProof(
            $method,
            $url->value,
            nonce: $this->nonce,
        );
    }

    /**
     * Keeps the private key out of dumps, the profiler included.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['nonce' => $this->nonce];
    }
}
