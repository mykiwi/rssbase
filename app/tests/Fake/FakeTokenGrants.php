<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\AtProto\JsonObject;
use App\AtProto\OAuth\Base64Url;

/**
 * The grants of the token endpoint: an authorization code, checked against PKCE, or a refresh token.
 * Both are single-use and bound to the DPoP key that obtained them.
 */
final readonly class FakeTokenGrants
{
    public function __construct(
        private FakeOAuthStore $store,
    ) {}

    /**
     * @return array<string, mixed> the token response
     */
    public function issue(JsonObject $body, string $jkt): array
    {
        $grant = match ($body->string('grant_type')) {
            'authorization_code' => $this->redeemCode($body, $jkt),
            'refresh_token' => $this->redeemRefreshToken($body, $jkt),
            default => throw new FakeOAuthError('unsupported_grant_type'),
        };

        return [
            'access_token' => 'at-' . bin2hex(random_bytes(8)),
            'token_type' => 'DPoP',
            'expires_in' => $this->store->accessTokenLifetime,
            'refresh_token' => $this->store->issueRefreshToken($grant),
            'scope' => $grant['scope'],
            'sub' => $grant['did'],
        ];
    }

    /**
     * @return array{did: string, scope: string, jkt: string, client_id: string}
     */
    private function redeemCode(JsonObject $body, string $jkt): array
    {
        $code = new JsonObject(
            $this->store->takeCode(FakeField::text($body, name: 'code')) ?? throw new FakeOAuthError('invalid_grant'),
        );
        $checks = [
            hash_equals(
                FakeField::text($code, name: 'code_challenge'),
                Base64Url::encode(hash('sha256', FakeField::text($body, name: 'code_verifier'), binary: true)),
            ),
            FakeField::text($code, name: 'redirect_uri') === FakeField::text($body, name: 'redirect_uri'),
            FakeField::text($code, name: 'client_id') === FakeField::text($body, name: 'client_id'),
            // The tokens are bound to the key that pushed the request.
            FakeField::text($code, name: 'jkt') === $jkt,
        ];

        if (\in_array(false, $checks, strict: true)) {
            throw new FakeOAuthError('invalid_grant');
        }

        return [
            'did' => FakeField::text($code, name: 'did'),
            'scope' => $this->store->grantedScope ?? FakeField::text($code, name: 'scope'),
            'jkt' => $jkt,
            'client_id' => FakeField::text($code, name: 'client_id'),
        ];
    }

    /**
     * @return array{did: string, scope: string, jkt: string, client_id: string}
     */
    private function redeemRefreshToken(JsonObject $body, string $jkt): array
    {
        $grant = $this->store->takeRefreshToken(FakeField::text(
            $body,
            name: 'refresh_token',
        )) ?? throw new FakeOAuthError('invalid_grant');

        if ($grant['jkt'] !== $jkt || $grant['client_id'] !== FakeField::text($body, name: 'client_id')) {
            throw new FakeOAuthError('invalid_grant');
        }

        return $grant;
    }
}
