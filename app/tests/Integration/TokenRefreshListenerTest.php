<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Security\AuthorizationFlow;
use App\Security\PostponedRefresh;
use App\Security\SessionRenewal;
use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * The listener of the application on the sessions of PHP, as in production: the storage of the
 * functional tests keeps no copy of a session under its old id.
 */
final class TokenRefreshListenerTest extends SessionKernelTestCase
{
    private const int GRACE = 300;

    private string $savePath;
    private string $oldId;
    private string $newId;

    protected function setUp(): void
    {
        FakeAtProtoNetwork::reset();
        $this->savePath = sys_get_temp_dir() . '/rssbase-sessions-' . bin2hex(random_bytes(4));
        new Filesystem()->mkdir($this->savePath);
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->savePath);

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheOldIdOfARenewedSessionIsRefusedOnceTheGraceHasPassed(): void
    {
        $this->renewSession(at: time() - self::GRACE - 1, tokensLastFor: 3600);

        $response = $this->visit($this->session($this->oldId));

        static::assertSame('/login', $response?->headers->get('Location'));
    }

    /**
     * The refresh token of the copy belongs to the new session: the provider would end both sessions
     * if the copy spent it.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheOldIdOfARenewedSessionNeverRefreshesTheTokens(): void
    {
        $this->renewSession(at: time(), tokensLastFor: 0);

        $response = $this->visit($this->session($this->oldId));

        static::assertNull($response);
        static::assertNotContains('POST ' . FakeAuthorizationServer::TOKEN_ENDPOINT, FakeAtProtoNetwork::requests());
    }

    /**
     * Two tabs: one renews the session while the other signs out with the id it still has.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSigningOutOnTheOldIdEndsTheRenewedSession(): void
    {
        $this->renewSession(at: time(), tokensLastFor: 3600);
        $old = $this->session($this->oldId);
        $this->signIn($old);
        self::service(Security::class)->logout(validateCsrfToken: false);
        $old->save();

        $response = $this->visit($this->session($this->newId));

        static::assertSame('/login', $response?->headers->get('Location'));
    }

    /**
     * An error that says nothing of the tokens may pass: the provider is asked again.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAFewRefusalsOfARefreshKeepTheUserSignedIn(): void
    {
        $session = $this->session();
        $flow = self::service(AuthorizationFlow::class);
        $flow->saveTokens($session, self::tokens(lastFor: 0));
        $flow->postponeRefresh($session, new PostponedRefresh(notBefore: time() - 1, refusals: 3));
        FakeAtProtoNetwork::refuseRequestsTo('auth.fake.test', status: 400, error: 'invalid_dpop_proof');

        $response = $this->visit($session);

        static::assertNull($response);
        static::assertSame(4, $flow->postponedRefresh($session)?->refusals);
    }

    /**
     * A provider that refuses every refresh no longer accepts the session, whatever error it names.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAProviderThatKeepsRefusingARefreshEndsTheSession(): void
    {
        $session = $this->session();
        $flow = self::service(AuthorizationFlow::class);
        $flow->saveTokens($session, self::tokens(lastFor: 0));
        $flow->postponeRefresh($session, new PostponedRefresh(notBefore: time() - 1, refusals: 4));
        FakeAtProtoNetwork::refuseRequestsTo('auth.fake.test', status: 400, error: 'invalid_dpop_proof');

        $response = $this->visit($session);

        static::assertSame('/login', $response?->headers->get('Location'));
    }

    /**
     * A provider that is down, or that receives too many requests, refuses nothing.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAProviderThatCannotAnswerIsNeverCountedAsRefusing(): void
    {
        $session = $this->session();
        $flow = self::service(AuthorizationFlow::class);
        $flow->saveTokens($session, self::tokens(lastFor: 0));
        $flow->postponeRefresh($session, new PostponedRefresh(notBefore: time() - 1, refusals: 4));
        FakeAtProtoNetwork::refuseRequestsTo('auth.fake.test', status: 429, error: 'RateLimitExceeded');

        $response = $this->visit($session);

        static::assertNull($response);
        static::assertSame(4, $flow->postponedRefresh($session)?->refusals);
    }

    /**
     * A session renewed at the given time: its old id keeps a marked copy. With tokens that last, only
     * the mark and the sign-out of the family can end it.
     */
    private function renewSession(int $at, int $tokensLastFor): void
    {
        $session = $this->session();
        self::service(AuthorizationFlow::class)->saveTokens($session, self::tokens($tokensLastFor));
        $this->oldId = $session->getId();
        self::service(SessionRenewal::class)->renew($session, $at);
        $this->newId = $session->getId();
        $session->save();
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
