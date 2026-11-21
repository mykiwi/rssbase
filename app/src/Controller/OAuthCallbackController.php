<?php

declare(strict_types=1);

namespace App\Controller;

use App\AtProto\OAuth\ClientIdentity;
use App\AtProto\OAuth\SignedState;
use App\AtProto\OAuth\Site;
use App\AtProto\OAuth\State;
use App\Security\LoginFailure;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The single callback of the OAuth client. On the site the visitor started from, AtProtoAuthenticator
 * handles it before this controller. On any other host, such as 127.0.0.1 for a loopback client, this
 * controller sends the answer of the provider, unchanged, to the site of the signed state.
 */
final class OAuthCallbackController extends AbstractController
{
    private const array LOOPBACK_HOSTS = ['127.0.0.1', '[::1]'];

    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private readonly ClientIdentity $clientIdentity,
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales,
    ) {}

    #[Route(ClientIdentity::CALLBACK_PATH, name: 'oauth_callback', methods: ['GET'])]
    public function __invoke(Request $request, SignedState $signedState): RedirectResponse
    {
        $site = $signedState->site(new State($request->query->getString('state')));

        if (null === $site || $site->equals(Site::ofRequest($request)) || !$this->isSiteOfRssBase($request, $site)) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, LoginFailure::failed());

            return $this->redirectToRoute('login');
        }

        return new RedirectResponse($site->url(
            ClientIdentity::CALLBACK_PATH . '?' . (string) $request->getQueryString(),
        ));
    }

    /**
     * A state is signed for the host of the request that asked for it, and a server may accept any
     * Host header: the signature alone would send the answer, and the visitor, to a foreign site.
     */
    private function isSiteOfRssBase(Request $request, Site $site): bool
    {
        // The callback of a loopback client is on 127.0.0.1, which is no site of RssBase: the site is
        // then on the machine of the visitor, under any local name.
        if (
            !$this->clientIdentity->isConfidential()
            && \in_array($request->getHost(), self::LOOPBACK_HOSTS, strict: true)
        ) {
            return $site->isLocal();
        }

        return array_any(
            $this->enabledLocales,
            fn(string $locale): bool => (
                $site->url(ClientIdentity::CALLBACK_PATH) === $this->generateUrl(
                    'oauth_callback',
                    ['_locale' => $locale],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                )
            ),
        );
    }
}
