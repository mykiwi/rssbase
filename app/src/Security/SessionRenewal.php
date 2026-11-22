<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Gives a session a new id, which renews the expiry of its cookie, without leaving the old id usable.
 *
 * The old id cannot be destroyed at once: a request that the browser sent before it received the new
 * id would find an empty session, and its answer would sign the user out. The old id is marked instead,
 * and refused once the requests on their way have passed.
 *
 * A session then has several copies for a while, under several ids. They share the name of their
 * family: signing out on one of them ends them all.
 *
 * @see https://www.php.net/manual/en/features.session.security.management.php#features.session.security.management.session-id-regeneration
 */
final readonly class SessionRenewal
{
    private const string SUPERSEDED_AT = 'atproto.superseded_at';
    private const string FAMILY = 'atproto.session_family';

    /**
     * The time the PHP manual suggests for a slow network.
     */
    private const int GRACE = 300;

    /**
     * How long a session lives without a visit: "gc_maxlifetime" in config/packages/framework.yaml.
     * A copy cannot outlive the memory of the sign-out of its family.
     */
    private const int SESSION_LIFETIME = 7_776_000;

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {}

    /**
     * @param int $now a Unix timestamp
     */
    public function renew(SessionInterface $session, int $now): void
    {
        if (null === self::family($session)) {
            $session->set(self::FAMILY, bin2hex(random_bytes(16)));
        }

        // PHP writes the session under the old id before it changes the id: the mark stays there only.
        $session->set(self::SUPERSEDED_AT, $now);

        try {
            $session->migrate();
        } finally {
            $session->remove(self::SUPERSEDED_AT);
        }
    }

    /**
     * Whether the session is the copy that a renewal left under the old id. Its refresh token belongs
     * to the new session: spending it here would end both.
     */
    public function isSuperseded(SessionInterface $session): bool
    {
        return $session->has(self::SUPERSEDED_AT);
    }

    /**
     * Whether the session is an old id past its time, or a copy of a session that signed out.
     */
    public function isRefused(SessionInterface $session, int $now): bool
    {
        $supersededAt = self::timestamp($session->get(self::SUPERSEDED_AT));

        if (null !== $supersededAt && ($now - $supersededAt) > self::GRACE) {
            return true;
        }

        $family = self::family($session);

        return null !== $family && $this->cache->hasItem(self::signedOutKey($family));
    }

    /**
     * The other copies of the session, which this request cannot reach, learn it at their next request.
     */
    public function signOut(SessionInterface $session): void
    {
        $family = self::family($session);

        if (null === $family) {
            return;
        }

        $item = $this->cache->getItem(self::signedOutKey($family));
        $this->cache->save($item->set(true)->expiresAfter(self::SESSION_LIFETIME));
    }

    private static function family(SessionInterface $session): ?string
    {
        return self::hexadecimal($session->get(self::FAMILY));
    }

    private static function timestamp(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }

    private static function hexadecimal(mixed $value): ?string
    {
        return \is_string($value) && ctype_xdigit($value) ? $value : null;
    }

    private static function signedOutKey(string $family): string
    {
        return "atproto.signed_out.{$family}";
    }
}
