<?php

declare(strict_types=1);

namespace App\Security;

use App\AtProto\Identity\Exception\AccountNotFound;
use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\OAuth\ClientIdentity;
use App\AtProto\OAuth\Exception\OAuthError;
use App\AtProto\OAuth\SignedState;
use App\AtProto\Profile\AvatarLocator;
use App\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Signs the visitor in when their provider sends them back with an authorization code.
 *
 * @see https://atproto.com/specs/oauth#authorization-flow
 */
final class AtProtoAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    private const string HANDLE = '_atproto_handle';

    public function __construct(
        private readonly SignedState $signedState,
        private readonly AuthorizationFlow $flow,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AccountRepository $accounts,
        private readonly AvatarLocator $avatarLocator,
    ) {}

    /**
     * Only on the site the visitor started from: another host has no session for them, and
     * OAuthCallbackController sends them back to it.
     */
    public function supports(Request $request): bool
    {
        return (
            ClientIdentity::CALLBACK_PATH === $request->getPathInfo()
            && $this->signedState->site($request->query->getString('state')) === $request->getSchemeAndHttpHost()
        );
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $pending = $this->flow->takePending($request);
        $request->attributes->set(self::HANDLE, $pending?->identity?->handle->value);

        $answered =
            null !== $pending
            && hash_equals($pending->state, $request->query->getString('state'))
            // The issuer bound to the request must be the one answering: another server could forge the answer.
            && $request->query->getString('iss') === $pending->server->issuer;

        if (!$answered) {
            throw LoginFailure::failed();
        }

        if ($request->query->has('error')) {
            throw 'access_denied' === $request->query->getString('error')
                ? LoginFailure::denied()
                : LoginFailure::failed();
        }

        try {
            $identity = $this->flow->complete($request->getSession(), $pending, $request->query->getString('code'));
        } catch (OAuthError|ProviderUnreachable $exception) {
            throw LoginFailure::providerUnreachable($exception);
        } catch (AccountNotFound $exception) {
            throw LoginFailure::failed($exception);
        }

        $canWrite = $this->flow->tokens($request->getSession())?->grantsWrite() ?? false;
        // Read once per login: the avatar lives as long as the session.
        $user = new AtProtoUser(
            $identity->did,
            $identity->handle->value,
            $this->avatarLocator->locate($identity),
            $canWrite,
        );

        return new SelfValidatingPassport(new UserBadge($identity->did, static fn(): AtProtoUser => $user));
    }

    public function onAuthenticationSuccess(
        Request $request,
        #[\SensitiveParameter]
        TokenInterface $token,
        string $firewallName,
    ): RedirectResponse {
        $user = $token->getUser();
        if ($user instanceof AtProtoUser) {
            $this->accounts->recordLogin($user->did, $user->handle);
        }

        $target = $this->getTargetPath($request->getSession(), $firewallName);
        $this->removeTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($target ?? $this->urlGenerator->generate('feeds'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        $session = $request->getSession();
        $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        $session->set(SecurityRequestAttributes::LAST_USERNAME, $request->attributes->get(self::HANDLE));

        return new RedirectResponse($this->urlGenerator->generate('login'));
    }
}
