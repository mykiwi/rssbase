<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\JsonObject;

/**
 * The answer of an authorization server to a request with a DPoP proof.
 */
final readonly class DpopResponse
{
    /**
     * @param DpopKey $dpopKey the key of the request, with the nonce the server gave in this answer
     */
    public function __construct(
        public int $status,
        public JsonObject $body,
        public DpopKey $dpopKey,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function asksForNonce(): bool
    {
        return 'use_dpop_nonce' === $this->body->string('error');
    }
}
