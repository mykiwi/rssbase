<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\HttpsUrl;
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
     * @throws OAuthError
     * @throws ProviderUnreachable
     */
    public function authorize(AuthorizationServer $server, State $state, ?Identity $identity): PushedAuthorization
    {
        $pending = PendingAuthorization::start($state, $server, $identity);
        $request = FormBody::of(
            response_type: 'code',
            code_challenge: $pending->codeChallenge(),
            code_challenge_method: 'S256',
            state: $state->value,
            redirect_uri: $this->clientIdentity->redirectUri(),
            scope: $this->clientIdentity->scope->value,
        );
        $request = null === $identity
            ? $request->with(prompt: 'create')
            : $request->with(login_hint: $identity->handle->value);

        $response = $this->post($server, $server->pushedAuthorizationRequestEndpoint, $request, $pending->dpopKey);
        $requestUri = $response->body->string('request_uri') ?? throw new ProviderUnreachable(\sprintf(
            '"%s" gave no request URI.',
            $server->issuer,
        ));
        $query = http_build_query(['client_id' => $this->clientIdentity->clientId(), 'request_uri' => $requestUri]);

        return new PushedAuthorization(
            $pending->withDpopKey($response->dpopKey),
            $server->authorizationEndpoint->withQuery($query),
        );
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
        $body = FormBody::of(
            grant_type: 'authorization_code',
            code: $code,
            redirect_uri: $this->clientIdentity->redirectUri(),
            code_verifier: $pending->codeVerifier,
        );
        $response = $this->post($pending->server, $pending->server->tokenEndpoint, $body, $pending->dpopKey);

        return TokenSet::fromResponse($response->body, $pending->server, $response->dpopKey);
    }

    /**
     * Refresh tokens are single-use: the caller must keep the new set, and only one refresh may run at a
     * time for a session.
     *
     * @throws OAuthError          when the provider refuses: OAuthError::endsAccess() tells whether
     *                             the same tokens may still refresh later
     * @throws ProviderUnreachable when the provider does not answer
     */
    public function refresh(TokenSet $tokens): TokenSet
    {
        $body = FormBody::of(grant_type: 'refresh_token', refresh_token: $tokens->refreshToken());
        $response = $this->post($tokens->server, $tokens->server->tokenEndpoint, $body, $tokens->dpopKey);
        $refreshed = TokenSet::fromResponse($response->body, $tokens->server, $response->dpopKey);

        if (!$refreshed->did->equals($tokens->did)) {
            throw new ProviderUnreachable(\sprintf(
                '"%s" refreshed the tokens of another account.',
                $tokens->server->issuer,
            ));
        }

        return $refreshed;
    }

    /**
     * Sends a request with a fresh DPoP proof, and sends it once more when the server asks for its nonce.
     */
    private function post(AuthorizationServer $server, HttpsUrl $url, FormBody $body, DpopKey $dpopKey): DpopResponse
    {
        $response = $this->send($server, $url, $body, $dpopKey);

        if ($response->asksForNonce() && $response->dpopKey->nonce !== $dpopKey->nonce) {
            $response = $this->send($server, $url, $body, $response->dpopKey);
        }

        if ($response->isSuccess()) {
            return $response;
        }

        $error = $response->body->string('error');

        // Only a refusal names an OAuth error. A server error, or a nonce the server asks for twice,
        // says nothing of the request: the same request may succeed later.
        if (null === $error || $response->status >= 500 || $response->asksForNonce()) {
            throw new ProviderUnreachable(\sprintf('"%s" answered %d.', $url, $response->status));
        }

        throw new OAuthError($error, $url);
    }

    private function send(AuthorizationServer $server, HttpsUrl $url, FormBody $body, DpopKey $dpopKey): DpopResponse
    {
        try {
            $response = $this->client->request('POST', $url->value, [
                'body' => $this->clientIdentity->authenticate($body, $server->issuer)->fields(),
                'headers' => ['DPoP' => $dpopKey->proof('POST', $url)],
            ]);

            // Servers must send a nonce on every response to a request with DPoP.
            $nonce = $response->getHeaders(throw: false)['dpop-nonce'][0] ?? throw new ProviderUnreachable(\sprintf(
                '"%s" answered without a DPoP nonce.',
                $url,
            ));

            return new DpopResponse(
                $response->getStatusCode(),
                new JsonObject($response->toArray(throw: false)),
                $dpopKey->withNonce($nonce),
            );
        } catch (ExceptionInterface $exception) {
            throw new ProviderUnreachable(\sprintf('"%s" does not answer.', $url), previous: $exception);
        }
    }
}
