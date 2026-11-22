<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Security\SessionRenewal;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * With the sessions of PHP, as in production: the storage of the functional tests keeps no copy of a
 * session under its old id.
 */
final class SessionRenewalTest extends TestCase
{
    private const int NOW = 1_800_000_000;
    private const int GRACE = 300;

    private string $savePath;
    private SessionRenewal $renewal;

    protected function setUp(): void
    {
        $this->savePath = sys_get_temp_dir() . '/rssbase-sessions-' . bin2hex(random_bytes(4));
        new Filesystem()->mkdir($this->savePath);
        $this->renewal = new SessionRenewal(new ArrayAdapter());
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->savePath);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheOldIdIsMarkedThenRefused(): void
    {
        $session = $this->session();
        $session->set('tokens', value: 'the tokens');
        $oldId = $session->getId();

        $this->renewal->renew($session, self::NOW);

        static::assertNotSame($oldId, $session->getId());
        static::assertFalse($this->renewal->isSuperseded($session));
        static::assertFalse($this->renewal->isRefused($session, self::NOW + self::GRACE + 1));
        static::assertSame('the tokens', $session->get('tokens'));
        $session->save();

        // A request that left the browser before the new id arrived still finds its session.
        $old = $this->session($oldId);
        static::assertSame('the tokens', $old->get('tokens'));
        static::assertTrue($this->renewal->isSuperseded($old));
        static::assertFalse($this->renewal->isRefused($old, self::NOW + self::GRACE));
        static::assertTrue($this->renewal->isRefused($old, self::NOW + self::GRACE + 1));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSigningOutOnTheOldIdEndsTheNewOne(): void
    {
        $session = $this->session();
        $oldId = $session->getId();
        $this->renewal->renew($session, self::NOW);
        $newId = $session->getId();
        $session->save();

        $old = $this->session($oldId);
        $this->renewal->signOut($old);
        $old->save();

        static::assertTrue($this->renewal->isRefused($this->session($newId), self::NOW));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSigningOutASessionThatWasNeverRenewedLeavesNoTrace(): void
    {
        $session = $this->session();

        $this->renewal->signOut($session);

        static::assertFalse($this->renewal->isRefused($session, self::NOW));
    }

    private function session(?string $id = null): Session
    {
        $session = new Session(new NativeSessionStorage(['save_path' => $this->savePath, 'use_cookies' => 0]));

        if (null !== $id) {
            $session->setId($id);
        }

        $session->start();

        return $session;
    }
}
