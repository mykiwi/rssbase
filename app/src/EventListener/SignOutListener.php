<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\SessionRenewal;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Signing out ends every copy of the session: a renewal may have left one under another id, which a
 * request on its way, or the answer of one, still uses.
 */
final readonly class SignOutListener
{
    public function __construct(
        private SessionRenewal $renewal,
    ) {}

    /**
     * Before the listener of Symfony that empties the session.
     */
    #[AsEventListener(dispatcher: 'security.event_dispatcher.main', priority: 64)]
    public function __invoke(LogoutEvent $event): void
    {
        if ($event->getRequest()->hasSession()) {
            $this->renewal->signOut($event->getRequest()->getSession());
        }
    }
}
