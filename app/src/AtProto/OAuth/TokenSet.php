<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\Identity\Did;
use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\JsonObject;

/**
 * The tokens of a signed-in account, bound to the DPoP key of the session, kept in the session.
 */
final readonly class TokenSet
{
    /**
     * @param Scope $scope what the provider granted, which may be less than what the client requested
     */
    private function __construct(
        public Did $did,
        public Scope $scope,
        public AuthorizationServer $server,
        public DpopKey $dpopKey,
        #[\SensitiveParameter]
        private TokenPair $tokens,
    ) {}

    /**
     * @throws ProviderUnreachable when the response is not what the AT Protocol profile requires
     *
     * @see https://atproto.com/specs/oauth#tokens-and-session-management
     */
    public static function fromResponse(JsonObject $response, AuthorizationServer $server, DpopKey $dpopKey): self
    {
        $did = Did::tryFrom($response->string('sub') ?? '');
        $scope = new Scope($response->string('scope') ?? '');
        $tokens = new TokenPair(
            $response->string('access_token') ?? '',
            $response->string('refresh_token') ?? '',
            time() + ($response->int('expires_in') ?? 0),
        );

        $checks = [
            // A provider that grants less than the identity did not confirm the account.
            $scope->confirmsIdentity(),
            0 === strcasecmp($response->string('token_type') ?? '', 'DPoP'),
            '' !== $tokens->access,
            '' !== $tokens->refresh,
        ];

        if (null === $did || \in_array(false, $checks, strict: true)) {
            throw new ProviderUnreachable(\sprintf(
                '"%s" answered tokens that the AT Protocol profile does not allow.',
                $server->issuer,
            ));
        }

        return new self($did, $scope, $server, $dpopKey, $tokens);
    }

    public function accessToken(): string
    {
        return $this->tokens->access;
    }

    public function refreshToken(): string
    {
        return $this->tokens->refresh;
    }

    public function isExpired(int $now): bool
    {
        return $this->tokens->isExpired($now);
    }

    /**
     * Keeps the tokens and the key out of dumps, the profiler included.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['did' => $this->did->value, 'scope' => $this->scope->value, 'issuer' => $this->server->issuer->value];
    }
}
