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
 * another language stays on the site the visitor is on, whatever its name.
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

    #[AsEventListener]
    public function __invoke(RequestEvent $event): void
    {
        $domain = $event->getRequest()->getHost();
        foreach ($this->enabledLocales as $locale) {
            if (!str_starts_with($domain, "{$locale}.")) {
                continue;
            }

            $domain = substr($domain, \strlen($locale) + 1);
            break;
        }

        $this->requestContext->setParameter('domain', $domain);
    }
}
