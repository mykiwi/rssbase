<?php

declare(strict_types=1);

namespace App\Controller;

use App\AtProto\OAuth\ClientIdentity;
use App\AtProto\OAuth\SignedState;
use App\Security\LoginFailure;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The single callback of the OAuth client. On the site the visitor started from, AtProtoAuthenticator
 * handles it before this controller. On any other host, such as 127.0.0.1 for a loopback client, this
 * controller sends the answer of the provider, unchanged, to the site of the signed state.
 */
final class OAuthCallbackController extends AbstractController
{
    #[Route(ClientIdentity::CALLBACK_PATH, name: 'oauth_callback', methods: ['GET'])]
    public function __invoke(Request $request, SignedState $signedState): RedirectResponse
    {
        $site = $signedState->site($request->query->getString('state'));

        if (null === $site || $site === $request->getSchemeAndHttpHost()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, LoginFailure::failed());

            return $this->redirectToRoute('login');
        }

        return new RedirectResponse($site . ClientIdentity::CALLBACK_PATH . '?' . (string) $request->getQueryString());
    }
}
