<?php

declare(strict_types=1);

namespace App\Security;

use App\AtProto\Identity\Did;
use App\AtProto\Identity\Exception\AccountNotFound;
use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\Identity\Identity;
use App\AtProto\Identity\IdentityResolver;
use App\AtProto\OAuth\AuthorizationServer;
use App\AtProto\OAuth\AuthorizationServerLocator;
use App\AtProto\OAuth\Exception\OAuthError;
use App\AtProto\OAuth\OAuthClient;
use App\AtProto\OAuth\PendingAuthorization;
use App\AtProto\OAuth\SignedState;
use App\AtProto\OAuth\Site;
use App\AtProto\OAuth\TokenSet;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The OAuth flow of a browser, whose state lives in its server-side session: the pending authorization
 * request, then the tokens of the signed-in account. One device, one session, one set of tokens.
 */
final readonly class AuthorizationFlow
{
    private const string PENDING = 'atproto.pending';
    private const string TOKENS = 'atproto.tokens';

    public function __construct(
        private OAuthClient $client,
        private SignedState $signedState,
        private IdentityResolver $identityResolver,
        private AuthorizationServerLocator $serverLocator,
    ) {}

    /**
     * Pushes an authorization request and sends the visitor to the provider.
     *
     * @throws OAuthError
     * @throws ProviderUnreachable
     */
    public function start(Request $request, AuthorizationServer $server, ?Identity $identity): RedirectResponse
    {
        $state = $this->signedState->issue(Site::ofRequest($request));
        $pushed = $this->client->authorize($server, $state, $identity);
        $request->getSession()->set(self::PENDING, $pushed->pending);

        return new RedirectResponse($pushed->redirectUrl->value);
    }

    /**
     * A pending request is answered once: taking it removes it from the session.
     */
    public function takePending(Request $request): ?PendingAuthorization
    {
        return self::ofClass($request->getSession()->remove(self::PENDING), PendingAuthorization::class);
    }

    /**
     * Exchanges the code, checks that the server authorized the expected account, and keeps the tokens.
     *
     * @throws AccountNotFound     when the account is not the expected one
     * @throws OAuthError
     * @throws ProviderUnreachable
     */
    public function complete(
        SessionInterface $session,
        PendingAuthorization $pending,
        #[\SensitiveParameter]
        string $code,
    ): Identity {
        $tokens = $this->client->exchangeCode($pending, $code);
        $identity = $this->confirmedIdentity($pending, $tokens->did);
        $session->set(self::TOKENS, $tokens);

        return $identity;
    }

    public function tokens(SessionInterface $session): ?TokenSet
    {
        return self::ofClass($session->get(self::TOKENS), TokenSet::class);
    }

    public function saveTokens(SessionInterface $session, TokenSet $tokens): void
    {
        $session->set(self::TOKENS, $tokens);
    }

    /**
     * The account the server authorized must be the one the visitor asked for. Starting from a provider,
     * the account is new to RssBase: its DID must lead back to the server that issued the tokens.
     *
     * @see https://atproto.com/specs/oauth#identity-authentication
     */
    private function confirmedIdentity(PendingAuthorization $pending, Did $did): Identity
    {
        if (null !== $pending->identity) {
            return $pending->identity->did->equals($did)
                ? $pending->identity
                : throw new AccountNotFound(\sprintf(
                    'The server authorized "%s" instead of "%s".',
                    $did,
                    $pending->identity->did,
                ));
        }

        $identity = $this->identityResolver->resolveDid($did);

        if (!$this->serverLocator->issuerOf($identity->pds)->equals($pending->server->issuer)) {
            throw new AccountNotFound(\sprintf('"%s" is not hosted by "%s".', $did, $pending->server->issuer));
        }

        return $identity;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ?T
     */
    private static function ofClass(mixed $value, string $class): ?object
    {
        return $value instanceof $class ? $value : null;
    }
}
