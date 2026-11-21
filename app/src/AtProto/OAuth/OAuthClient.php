<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\Identity\Identity;
use App\AtProto\JsonObject;
use App\AtProto\OAuth\Exception\OAuthError;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The AT Protocol profile of OAuth: pushed authorization requests, PKCE, and DPoP on every request to
 * the authorization server, with the nonce the server requires.
 *
 * @see https://atproto.com/specs/oauth
 */
final readonly class OAuthClient
{
    public function __construct(
        #[Autowire(service: 'atproto.client')]
        private HttpClientInterface $client,
        private ClientIdentity $clientIdentity,
    ) {}

    /**
     * Pushes an authorization request.
     *
     * @param ?Identity $identity the account the visitor typed the handle of, or null to let them create
     *                            an account at the provider
     *
     * @return array{PendingAuthorization, string} the request, and the URL to send the visitor to
     *
     * @throws OAuthError
     * @throws ProviderUnreachable
     */
    public function authorize(AuthorizationServer $server, string $state, ?Identity $identity): array
    {
        $pending = PendingAuthorization::start($state, $server, $identity);
        $parameters = [
            'response_type' => 'code',
            'code_challenge' => $pending->codeChallenge(),
            'code_challenge_method' => 'S256',
            'state' => $state,
            'redirect_uri' => $this->clientIdentity->redirectUri(),
            'scope' => $this->clientIdentity->scope,
        ];
        $parameters += null === $identity ? ['prompt' => 'create'] : ['login_hint' => $identity->handle->value];

        [$response, $dpopKey] = $this->post(
            $server,
            $server->pushedAuthorizationRequestEndpoint,
            $parameters,
            $pending->dpopKey,
        );
        $requestUri = $response->string('request_uri') ?? throw new ProviderUnreachable(\sprintf(
            '"%s" gave no request URI.',
            $server->issuer,
        ));
        $query = http_build_query(['client_id' => $this->clientIdentity->clientId(), 'request_uri' => $requestUri]);

        return [$pending->withDpopKey($dpopKey), "{$server->authorizationEndpoint}?{$query}"];
    }

    /**
     * The redirect URI is computed again, the same as when the request was pushed: the visitor comes
     * back to the site they started from.
     *
     * @throws OAuthError
     * @throws ProviderUnreachable
     */
    public function exchangeCode(PendingAuthorization $pending, #[\SensitiveParameter] string $code): TokenSet
    {
        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->clientIdentity->redirectUri(),
            'code_verifier' => $pending->codeVerifier,
        ];
        [$response, $dpopKey] = $this->post(
            $pending->server,
            $pending->server->tokenEndpoint,
            $body,
            $pending->dpopKey,
        );

        return TokenSet::fromResponse($response, $pending->server, $dpopKey);
    }

    /**
     * Refresh tokens are single-use: the caller must keep the new set, and only one refresh may run at a
     * time for a session.
     *
     * @throws OAuthError          "invalid_grant" when the provider ended the access
     * @throws ProviderUnreachable
     */
    public function refresh(TokenSet $tokens): TokenSet
    {
        $body = ['grant_type' => 'refresh_token', 'refresh_token' => $tokens->refreshToken()];
        [$response, $dpopKey] = $this->post($tokens->server, $tokens->server->tokenEndpoint, $body, $tokens->dpopKey);
        $refreshed = TokenSet::fromResponse($response, $tokens->server, $dpopKey);

        if ($refreshed->did !== $tokens->did) {
            throw new ProviderUnreachable(\sprintf(
                '"%s" refreshed the tokens of another account.',
                $tokens->server->issuer,
            ));
        }

        return $refreshed;
    }

    /**
     * Sends a request with a fresh DPoP proof, and sends it once more when the server asks for its nonce.
     *
     * @param array<string, string> $body
     *
     * @return array{JsonObject, DpopKey} the response, and the key with the last nonce of the server
     */
    private function post(AuthorizationServer $server, string $url, array $body, DpopKey $dpopKey): array
    {
        [$status, $response, $nonce] = $this->send($server, $url, $body, $dpopKey);

        if ('use_dpop_nonce' === $response->string('error') && $nonce !== $dpopKey->nonce) {
            $dpopKey = $dpopKey->withNonce($nonce);
            [$status, $response, $nonce] = $this->send($server, $url, $body, $dpopKey);
        }

        if ($status >= 200 && $status < 300) {
            return [$response, $dpopKey->withNonce($nonce)];
        }

        throw new OAuthError($response->string('error') ?? "HTTP {$status}", $url);
    }

    /**
     * @param array<string, string> $body
     *
     * @return array{int, JsonObject, string} the status, the body and the DPoP nonce of the response
     */
    private function send(AuthorizationServer $server, string $url, array $body, DpopKey $dpopKey): array
    {
        try {
            $response = $this->client->request('POST', $url, [
                'body' => $this->clientIdentity->authenticate($body, $server->issuer),
                'headers' => ['DPoP' => $dpopKey->proof('POST', $url)],
            ]);

            // Servers must send a nonce on every response to a request with DPoP.
            $nonce = $response->getHeaders(throw: false)['dpop-nonce'][0] ?? throw new ProviderUnreachable(\sprintf(
                '"%s" answered without a DPoP nonce.',
                $url,
            ));

            return [$response->getStatusCode(), new JsonObject($response->toArray(throw: false)), $nonce];
        } catch (ExceptionInterface $exception) {
            throw new ProviderUnreachable(\sprintf('"%s" does not answer.', $url), previous: $exception);
        }
    }
}
