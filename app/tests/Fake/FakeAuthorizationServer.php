<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\AtProto\JsonObject;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The authorization server of the fake network, written from https://atproto.com/specs/oauth: it
 * refuses a request without PKCE S256, without a valid DPoP proof and nonce, or, for a confidential
 * client, without a valid assertion. Its state is static, like the rest of the fake network.
 */
final class FakeAuthorizationServer
{
    public const string ISSUER = 'https://auth.fake.test';
    public const string AUTHORIZATION_ENDPOINT = self::ISSUER . '/oauth/authorize';
    public const string PAR_ENDPOINT = self::ISSUER . '/oauth/par';
    public const string TOKEN_ENDPOINT = self::ISSUER . '/oauth/token';

    private static ?FakeOAuthStore $store = null;

    public static function reset(): void
    {
        self::$store = new FakeOAuthStore();
    }

    /**
     * @return array<string, mixed>
     */
    public static function metadata(): array
    {
        return [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::AUTHORIZATION_ENDPOINT,
            'token_endpoint' => self::TOKEN_ENDPOINT,
            'pushed_authorization_request_endpoint' => self::PAR_ENDPOINT,
            'require_pushed_authorization_requests' => true,
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'private_key_jwt'],
            'token_endpoint_auth_signing_alg_values_supported' => ['ES256'],
            'scopes_supported' => ['atproto', 'transition:generic'],
            'authorization_response_iss_parameter_supported' => true,
            'dpop_signing_alg_values_supported' => ['ES256'],
            'client_id_metadata_document_supported' => true,
            'prompt_values_supported' => ['none', 'login', 'consent', 'select_account', 'create'],
        ];
    }

    /**
     * @param array<array-key, mixed> $publicKey
     */
    public static function trustClient(string $clientId, array $publicKey): void
    {
        self::store()->clientKeys[$clientId] = $publicKey;
    }

    /**
     * The server grants this scope whatever the client asks, like a provider that drops the
     * permissions it does not know.
     */
    public static function grantOnly(string $scope): void
    {
        self::store()->grantedScope = $scope;
    }

    public static function issueAccessTokensFor(int $seconds): void
    {
        self::store()->accessTokenLifetime = $seconds;
    }

    /**
     * The account withdraws its approval: its refresh tokens stop working.
     */
    public static function revoke(string $did): void
    {
        self::store()->revoke($did);
    }

    /**
     * @return array<array-key, string> the parameters of the pushed request that the authorization URL names
     */
    public static function pushedRequest(string $authorizationUrl): array
    {
        $query = [];
        parse_str((string) parse_url($authorizationUrl, \PHP_URL_QUERY), $query);
        $query = new JsonObject($query);
        $request = self::store()->request($query->string('request_uri') ?? '') ?? throw new \RuntimeException(
            'Unknown request URI.',
        );

        if ($query->string('client_id') !== ($request['client_id'] ?? null)) {
            throw new \RuntimeException('The authorization URL names another client.');
        }

        return $request;
    }

    /**
     * The account approves the client, or refuses when no DID is given.
     *
     * @return string the URL the server then sends the browser to
     */
    public static function answer(string $authorizationUrl, ?string $did): string
    {
        $request = self::pushedRequest($authorizationUrl);
        $result = null === $did
            ? ['error' => 'access_denied']
            : ['code' => self::store()->issueCode([...$request, 'did' => $did])];

        return ($request['redirect_uri'] ?? '')
        . '?'
        . http_build_query([...$result, 'state' => $request['state'] ?? '', 'iss' => self::ISSUER]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function handle(string $url, array $options): MockResponse
    {
        return new FakeOAuthEndpoints(self::store())->handle($url, $options);
    }

    private static function store(): FakeOAuthStore
    {
        return self::$store ??= new FakeOAuthStore();
    }
}
