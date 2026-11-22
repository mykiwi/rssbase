<?php

declare(strict_types=1);

namespace App\Controller;

use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\OAuth\AuthorizationServerLocator;
use App\AtProto\OAuth\Exception\OAuthError;
use App\AtProto\Provider\ProviderCatalog;
use App\AtProto\Provider\Region;
use App\Security\AuthorizationFlow;
use App\Security\LoginFailure;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Where a visitor without an account picks a provider, creates an account there and comes back signed in.
 */
final class ProviderController extends AbstractController
{
    public function __construct(
        private readonly ProviderCatalog $catalog,
        #[Autowire(param: 'app.atproto.providers_directory_url')]
        private readonly string $directoryUrl,
        #[Autowire(param: 'app.atproto.self_hosting_url')]
        private readonly string $selfHostingUrl,
    ) {}

    #[Route('/providers', name: 'providers', methods: ['GET'])]
    public function list(#[MapQueryParameter] ?Region $region = null): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('feeds');
        }

        return $this->renderList($region, error: null);
    }

    /**
     * Straight to the provider, with no screen in between: the visitor creates the account there and
     * approves RssBase in the same visit.
     */
    #[Route('/providers/{id}', name: 'provider_start', requirements: ['id' => '[a-z]+'], methods: ['POST'])]
    public function start(
        string $id,
        Request $request,
        AuthorizationServerLocator $serverLocator,
        AuthorizationFlow $flow,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('feeds');
        }

        $provider = $this->catalog->find($id) ?? throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('provider', $request->request->getString('_csrf_token'))) {
            return $this->renderList(region: null, error: LoginFailure::failed()->getMessageKey());
        }

        try {
            return $flow->start($request, $serverLocator->forIssuer($provider->issuer), identity: null);
        } catch (OAuthError|ProviderUnreachable) {
            return $this->renderList(region: null, error: LoginFailure::providerUnreachable()->getMessageKey());
        }
    }

    private function renderList(?Region $region, ?string $error): Response
    {
        return $this->render(
            'provider/index.html.twig',
            [
                // All of them: the filter shows a region in the page, without asking the server.
                'providers' => $this->catalog->all(),
                'region' => $region,
                'directory_url' => $this->directoryUrl,
                'self_hosting_url' => $this->selfHostingUrl,
                'error' => $error,
            ],
            new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }
}
