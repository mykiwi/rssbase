<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\EventListener\LocaleDomainListener;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * With the sessions of PHP, as in production: PHP refuses a session setting once a session is active.
 */
final class LocaleDomainListenerTest extends TestCase
{
    private string $savePath;

    protected function setUp(): void
    {
        $this->savePath = sys_get_temp_dir() . '/rssbase-sessions-' . bin2hex(random_bytes(4));
        new Filesystem()->mkdir($this->savePath);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->savePath);
    }

    /**
     * Symfony renders an error page in a sub-request, when the session of the visitor is already open.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testASubRequestLeavesTheCookieOfAnOpenSessionAlone(): void
    {
        $listener = new LocaleDomainListener(new RequestContext(), ['en', 'fr']);
        $request = Request::create('http://fr.rssbase.localhost/feeds');

        $listener($this->event($request, HttpKernelInterface::MAIN_REQUEST));
        static::assertSame('rssbase.localhost', \ini_get('session.cookie_domain'));

        new Session(new NativeSessionStorage(['save_path' => $this->savePath, 'use_cookies' => 0]))->start();
        $listener($this->event($request, HttpKernelInterface::SUB_REQUEST));

        static::assertSame('rssbase.localhost', \ini_get('session.cookie_domain'));
    }

    private function event(Request $request, int $type): RequestEvent
    {
        return new RequestEvent(static::createStub(HttpKernelInterface::class), $request, $type);
    }
}
