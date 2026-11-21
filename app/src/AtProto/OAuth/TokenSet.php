<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\JsonObject;

/**
 * The tokens of a signed-in account, bound to the DPoP key of the session, kept in the session.
 */
final readonly class TokenSet
{
    /**
     * Refreshed this long before they expire, so that a token sent to a server does not expire on the way.
     */
    private const int LEEWAY = 30;

    /**
     * @param non-empty-string                                        $did
     * @param array{access: string, refresh: string, expiresAt: int} $tokens
     */
    private function __construct(
        public string $did,
        public string $scope,
        public AuthorizationServer $server,
        public DpopKey $dpopKey,
        #[\SensitiveParameter]
        private array $tokens,
    ) {}

    /**
     * @throws ProviderUnreachable when the response is not what the AT Protocol profile requires
     *
     * @see https://atproto.com/specs/oauth#tokens-and-session-management
     */
    public static function fromResponse(JsonObject $response, AuthorizationServer $server, DpopKey $dpopKey): self
    {
        $did = $response->string('sub') ?? '';
        $scope = $response->string('scope') ?? '';
        $tokens = [
            'access' => $response->string('access_token') ?? '',
            'refresh' => $response->string('refresh_token') ?? '',
            'expiresAt' => time() + ($response->int('expires_in') ?? 0),
        ];

        $checks = [
            str_starts_with($did, 'did:'),
            // A provider that grants less than "atproto" did not confirm the identity of the account.
            \in_array('atproto', explode(' ', $scope), strict: true),
            0 === strcasecmp($response->string('token_type') ?? '', 'DPoP'),
            '' !== $tokens['access'],
            '' !== $tokens['refresh'],
        ];

        if (\in_array(false, $checks, strict: true) || '' === $did) {
            throw new ProviderUnreachable(\sprintf(
                '"%s" answered tokens that the AT Protocol profile does not allow.',
                $server->issuer,
            ));
        }

        return new self($did, $scope, $server, $dpopKey, $tokens);
    }

    public function accessToken(): string
    {
        return $this->tokens['access'];
    }

    public function refreshToken(): string
    {
        return $this->tokens['refresh'];
    }

    public function isExpired(int $now): bool
    {
        return $now >= ($this->tokens['expiresAt'] - self::LEEWAY);
    }

    /**
     * Whether the provider granted a write permission. A provider that does not know the requested
     * permissions grants only "atproto": the identity of the account.
     */
    public function grantsWrite(): bool
    {
        foreach (explode(' ', $this->scope) as $scope) {
            if (str_starts_with($scope, 'repo:') || 'transition:generic' === $scope) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keeps the tokens and the key out of dumps, the profiler included.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['did' => $this->did, 'scope' => $this->scope, 'issuer' => $this->server->issuer];
    }
}
