<?php

declare(strict_types=1);

namespace App\EventListener;

use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\OAuth\Exception\OAuthError;
use App\AtProto\OAuth\OAuthClient;
use App\Security\AtProtoUser;
use App\Security\AuthorizationFlow;
use App\Security\LoginFailure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Keeps the tokens of a signed-in account fresh, and ends the session when the provider no longer
 * accepts them: the user revoked RssBase, or the session expired at the provider.
 *
 * The session is open while the tokens refresh, and PHP locks it: two requests of one browser cannot
 * spend the same single-use refresh token.
 */
final readonly class TokenRefreshListener
{
    /**
     * How long the requests of a user do not wait again for a provider that did not answer.
     */
    private const int RETRY_DELAY = 60;

    public function __construct(
        private Security $security,
        private AuthorizationFlow $flow,
        private OAuthClient $client,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * After the firewall, which loads the user from the session.
     */
    #[AsEventListener(priority: 4)]
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->security->getUser() instanceof AtProtoUser) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $now = time();

        if (!$this->isDue($session, $now)) {
            return;
        }

        try {
            $this->refresh($session);
        } catch (OAuthError) {
            // The provider refuses the refresh token, or no longer knows the client: asking again
            // gives the same answer.
            $this->endSession($event);
        } catch (ProviderUnreachable) {
            // The provider is down, not the access: the user stays signed in.
            $this->flow->postponeRefresh($session, new \DateTimeImmutable('@' . ($now + self::RETRY_DELAY)));
        }
    }

    private function isDue(SessionInterface $session, int $now): bool
    {
        return true === $this->flow->tokens($session)?->isExpired($now) && $this->flow->mayRefresh($session, $now);
    }

    /**
     * @throws OAuthError
     * @throws ProviderUnreachable
     */
    private function refresh(SessionInterface $session): void
    {
        $tokens = $this->flow->tokens($session);

        if (null === $tokens) {
            return;
        }

        $this->flow->saveTokens($session, $this->client->refresh($tokens));
        // A new id renews the expiry of the session cookie: an active session does not expire.
        $session->migrate();
    }

    private function endSession(RequestEvent $event): void
    {
        $this->security->logout(validateCsrfToken: false);
        $event->getRequest()->getSession()->set(
            SecurityRequestAttributes::AUTHENTICATION_ERROR,
            LoginFailure::sessionEnded(),
        );
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('login')));
    }
}
