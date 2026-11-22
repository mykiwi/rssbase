<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RequestContext;

/**
 * Routes carry the language in a subdomain: "fr.{domain}" for French, "{domain}" for English.
 * This listener gives the URL generator the domain of the current request, so that a link to
 * another language stays on the site the visitor is on, whatever its name. It also puts the session
 * cookie on that domain, so that a login serves both languages.
 */
final class LocaleDomainListener
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private readonly RequestContext $requestContext,
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales,
    ) {}

    /**
     * Before the firewall, which starts the session: PHP refuses a cookie setting once a session is active.
     */
    #[AsEventListener(priority: 16)]
    public function __invoke(RequestEvent $event): void
    {
        // A sub-request, such as the one that renders an error page, has the host of the main request,
        // and the session may be open by then.
        if (!$event->isMainRequest()) {
            return;
        }

        $domain = $event->getRequest()->getHost();
        foreach ($this->enabledLocales as $locale) {
            if (!str_starts_with($domain, "{$locale}.")) {
                continue;
            }

            $domain = substr($domain, \strlen($locale) + 1);
            break;
        }

        $this->requestContext->setParameter('domain', $domain);

        // Set on each request, as a worker keeps the settings of the previous one. The session options of
        // Symfony are fixed when the container is built, and the domain depends on the request.
        // @mago-expect lint:no-ini-set
        ini_set('session.cookie_domain', self::sharesCookies($domain) ? $domain : '');
    }

    /**
     * Browsers refuse a cookie for an IP address or for a name of one label, such as "localhost":
     * such a site keeps a cookie of its own host, and its languages do not share the session.
     */
    private static function sharesCookies(string $domain): bool
    {
        return str_contains($domain, '.') && false === filter_var(trim($domain, characters: '[]'), \FILTER_VALIDATE_IP);
    }
}
