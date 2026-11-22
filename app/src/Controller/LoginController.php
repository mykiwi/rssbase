<?php

declare(strict_types=1);

namespace App\Controller;

use App\AtProto\Identity\Exception\AccountNotFound;
use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\Identity\Handle;
use App\AtProto\Identity\IdentityResolver;
use App\AtProto\OAuth\AuthorizationServerLocator;
use App\AtProto\OAuth\Exception\OAuthError;
use App\Security\AuthorizationFlow;
use App\Security\LoginFailure;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function show(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('feeds');
        }

        return $this->renderForm(
            $authenticationUtils->getLastUsername(),
            $authenticationUtils->getLastAuthenticationError(),
        );
    }

    #[Route('/login', name: 'login_start', methods: ['POST'])]
    public function start(
        Request $request,
        IdentityResolver $identityResolver,
        AuthorizationServerLocator $serverLocator,
        AuthorizationFlow $flow,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('feeds');
        }

        $input = $request->request->getString('handle');

        if (!$this->isCsrfTokenValid('login', $request->request->getString('_csrf_token'))) {
            return $this->renderForm($input, LoginFailure::failed());
        }

        try {
            $identity = $identityResolver->resolveHandle(Handle::fromInput($input));

            return $flow->start($request, $serverLocator->forPds($identity->pds), $identity);
        } catch (AccountNotFound $exception) {
            return $this->renderForm($input, LoginFailure::accountNotFound($exception));
        } catch (OAuthError|ProviderUnreachable $exception) {
            return $this->renderForm($input, LoginFailure::providerUnreachable($exception));
        }
    }

    /**
     * @param string $typed what the visitor typed, shown again as it was
     */
    private function renderForm(
        string $typed,
        ?\Throwable $error,
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ): Response {
        return $this->render(
            'login/index.html.twig',
            [
                'handle' => $typed,
                'error' => match (true) {
                    null === $error => null,
                    $error instanceof LoginFailure => $error->getMessageKey(),
                    default => LoginFailure::failed()->getMessageKey(),
                },
            ],
            new Response(status: null === $error ? Response::HTTP_OK : $status),
        );
    }
}
