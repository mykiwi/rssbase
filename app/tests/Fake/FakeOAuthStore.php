<?php

declare(strict_types=1);

namespace App\Tests\Fake;

/**
 * What the fake authorization server remembers: pushed requests, codes, refresh tokens and the
 * identifiers of the JWTs it already accepted.
 */
final class FakeOAuthStore
{
    public string $nonce;

    public ?string $grantedScope = null;

    public int $accessTokenLifetime = 300;

    /** @var array<string, array<array-key, mixed>> the public key of each confidential client */
    public array $clientKeys = [];

    /** @var array<string, array<array-key, string>> */
    private array $requests = [];

    /** @var array<string, array<array-key, string>> */
    private array $codes = [];

    /** @var array<string, array{did: string, scope: string, jkt: string, client_id: string}> */
    private array $refreshTokens = [];

    /** @var array<string, true> */
    private array $usedJtis = [];

    public function __construct()
    {
        $this->nonce = 'nonce-' . bin2hex(random_bytes(4));
    }

    /**
     * @param array<array-key, string> $request
     */
    public function push(array $request): string
    {
        $requestUri = 'urn:ietf:params:oauth:request_uri:req-' . bin2hex(random_bytes(8));
        $this->requests[$requestUri] = $request;

        return $requestUri;
    }

    /**
     * @return array<array-key, string>|null
     */
    public function request(string $requestUri): ?array
    {
        return $this->requests[$requestUri] ?? null;
    }

    /**
     * @param array<array-key, string> $grant
     */
    public function issueCode(array $grant): string
    {
        $code = 'code-' . bin2hex(random_bytes(8));
        $this->codes[$code] = $grant;

        return $code;
    }

    /**
     * Codes and refresh tokens are single-use: taking one removes it.
     *
     * @return array<array-key, string>|null
     */
    public function takeCode(#[\SensitiveParameter] string $code): ?array
    {
        $grant = $this->codes[$code] ?? null;
        unset($this->codes[$code]);

        return $grant;
    }

    /**
     * @param array{did: string, scope: string, jkt: string, client_id: string} $grant
     */
    public function issueRefreshToken(array $grant): string
    {
        $token = 'rt-' . bin2hex(random_bytes(8));
        $this->refreshTokens[$token] = $grant;

        return $token;
    }

    /**
     * @return array{did: string, scope: string, jkt: string, client_id: string}|null
     */
    public function takeRefreshToken(#[\SensitiveParameter] string $token): ?array
    {
        $grant = $this->refreshTokens[$token] ?? null;
        unset($this->refreshTokens[$token]);

        return $grant;
    }

    public function revoke(string $did): void
    {
        $this->refreshTokens = array_filter(
            $this->refreshTokens,
            static fn(array $grant): bool => $grant['did'] !== $did,
        );
    }

    /**
     * @return bool false when a JWT with this identifier was already accepted: a replay
     */
    public function useJti(string $jti): bool
    {
        if ('' === $jti || \array_key_exists($jti, $this->usedJtis)) {
            return false;
        }

        $this->usedJtis[$jti] = true;

        return true;
    }
}
