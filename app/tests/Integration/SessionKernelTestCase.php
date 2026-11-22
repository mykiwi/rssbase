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
use App\Tests\Fake\FakeAtProtoNetwork;
use App\Tests\Fake\FakeAuthorizationServer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Hands a session to the listeners of the application as the firewall does for a signed-in user,
 * for the tests that need a session the browser of the functional tests cannot have.
 */
abstract class SessionKernelTestCase extends KernelTestCase
{
    /**
     * The tokens of the fake provider for Alice, which expire after the given number of seconds.
     */
    protected static function tokens(int $lastFor): TokenSet
    {
        return TokenSet::fromResponse(
            new JsonObject([
                'sub' => FakeAtProtoNetwork::ALICE_DID,
                'scope' => 'atproto',
                'token_type' => 'DPoP',
                'access_token' => bin2hex(random_bytes(8)),
                'refresh_token' => bin2hex(random_bytes(8)),
                'expires_in' => $lastFor,
            ]),
            self::service(AuthorizationServerLocator::class)
                ->forIssuer(Issuer::fromString(FakeAuthorizationServer::ISSUER)),
            DpopKey::generate(),
        );
    }

    /**
     * @return ?Response what the listener answers instead of the page, if anything
     */
    protected function visit(Session $session): ?Response
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
    protected function signIn(Session $session): Request
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

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        static::assertInstanceOf($id, $service);

        return $service;
    }
}
