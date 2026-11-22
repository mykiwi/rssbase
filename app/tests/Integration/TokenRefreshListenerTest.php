<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\AtProto\Identity\Did;
use App\AtProto\Identity\Handle;
use App\AtProto\JsonObject;
use App\AtProto\OAuth\AuthorizationServerLocator;
use App\AtProto\OAuth\DpopKey;
use App\AtProto\OAuth\Issuer;
use App\AtProto\OAuth\TokenSet;
use App\EventListener\TokenRefreshListener;
use App\Security\AtProtoUser;
use App\Security\AuthorizationFlow;
use App\Security\SessionRenewal;
use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * The listener of the application on the sessions of PHP, as in production: the storage of the
 * functional tests keeps no copy of a session under its old id.
 */
final class TokenRefreshListenerTest extends KernelTestCase
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
     * A session renewed at the given time: its old id keeps a marked copy. With tokens that last, only
     * the mark and the sign-out of the family can end it.
     */
    private function renewSession(int $at, int $tokensLastFor): void
    {
        $session = $this->session();
        $server = self::service(AuthorizationServerLocator::class)
            ->forIssuer(Issuer::fromString(FakeAuthorizationServer::ISSUER));
        self::service(AuthorizationFlow::class)
            ->saveTokens($session, TokenSet::fromResponse(
                new JsonObject([
                    'sub' => FakeAtProtoNetwork::ALICE_DID,
                    'scope' => 'atproto',
                    'token_type' => 'DPoP',
                    'access_token' => bin2hex(random_bytes(8)),
                    'refresh_token' => bin2hex(random_bytes(8)),
                    'expires_in' => $tokensLastFor,
                ]),
                $server,
                DpopKey::generate(),
            ));
        $this->oldId = $session->getId();
        self::service(SessionRenewal::class)->renew($session, $at);
        $this->newId = $session->getId();
        $session->save();
    }

    /**
     * @return ?Response what the listener answers instead of the page, if anything
     */
    private function visit(Session $session): ?Response
    {
        $event = new RequestEvent(
            self::$kernel ?? throw new \LogicException('The kernel is not booted.'),
            $this->signIn($session),
            HttpKernelInterface::MAIN_REQUEST,
        );
        self::service(TokenRefreshListener::class)($event);
        $session->save();

        return $event->getResponse();
    }

    /**
     * The firewall has loaded the user of the session, and this is the request being handled.
     */
    private function signIn(Session $session): Request
    {
        $request = Request::create('http://rssbase.localhost/feeds');
        $request->setSession($session);
        self::service(RequestStack::class)->push($request);
        self::service(TokenStorageInterface::class)
            ->setToken(
                new PostAuthenticationToken(
                    new AtProtoUser(
                        Did::fromString(FakeAtProtoNetwork::ALICE_DID),
                        Handle::fromString(FakeAtProtoNetwork::ALICE),
                    ),
                    'main',
                    ['ROLE_USER'],
                ),
            );

        return $request;
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

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        static::assertInstanceOf($id, $service);

        return $service;
    }
}
