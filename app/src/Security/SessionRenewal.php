<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Gives a session a new id, which renews the expiry of its cookie, without leaving the old id usable.
 *
 * The old id cannot be destroyed at once: a request that the browser sent before it received the new
 * id would find an empty session, and its answer would sign the user out. The old id is marked instead,
 * and refused once the requests on their way have passed.
 *
 * @see https://www.php.net/manual/en/features.session.security.management.php#features.session.security.management.session-id-regeneration
 */
final readonly class SessionRenewal
{
    private const string SUPERSEDED_AT = 'atproto.superseded_at';

    /**
     * The time the PHP manual suggests for a slow network.
     */
    private const int GRACE = 300;

    /**
     * @param int $now a Unix timestamp
     */
    public function renew(SessionInterface $session, int $now): void
    {
        // PHP writes the session under the old id before it changes the id: the mark stays there only.
        $session->set(self::SUPERSEDED_AT, $now);
        $session->migrate();
        $session->remove(self::SUPERSEDED_AT);
    }

    /**
     * Whether the session is the copy that a renewal left under the old id. Its refresh token belongs
     * to the new session: spending it here would end both.
     */
    public function isSuperseded(SessionInterface $session): bool
    {
        return $session->has(self::SUPERSEDED_AT);
    }

    public function isRefused(SessionInterface $session, int $now): bool
    {
        $supersededAt = self::timestamp($session->get(self::SUPERSEDED_AT));

        return null !== $supersededAt && ($now - $supersededAt) > self::GRACE;
    }

    private static function timestamp(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }
}
