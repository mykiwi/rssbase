<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\HttpsUrl;
use App\AtProto\Identity\Exception\ProviderUnreachable;
use App\AtProto\Identity\Pds;
use App\AtProto\JsonObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Finds the authorization server of a PDS, and checks that it follows the AT Protocol profile of OAuth.
 *
 * @see https://atproto.com/specs/oauth#authorization-servers
 */
final readonly class AuthorizationServerLocator
{
    public function __construct(
        #[Autowire(service: 'atproto.client')]
        private HttpClientInterface $client,
    ) {}

    /**
     * @throws ProviderUnreachable
     */
    public function forPds(Pds $pds): AuthorizationServer
    {
        return $this->forIssuer($this->issuerOf($pds));
    }

    /**
     * @return Issuer the authorization server that the PDS names
     *
     * @throws ProviderUnreachable
     */
    public function issuerOf(Pds $pds): Issuer
    {
        $servers = $this->fetch($pds->url('/.well-known/oauth-protected-resource'))->strings('authorization_servers');
        $issuer = 1 === \count($servers) ? Issuer::tryFrom($servers[0]) : null;

        return $issuer ?? throw new ProviderUnreachable(\sprintf('"%s" does not name one authorization server.', $pds));
    }

    /**
     * @throws ProviderUnreachable
     */
    public function forIssuer(Issuer $issuer): AuthorizationServer
    {
        $metadata = $this->fetch($issuer->metadataUrl());

        // The issuer must be the origin the metadata came from, or another server could speak for it.
        if ($metadata->string('issuer') !== $issuer->value) {
            throw new ProviderUnreachable(\sprintf('The metadata of "%s" names another issuer.', $issuer));
        }

        // An endpoint outside https is no endpoint: plain http would also escape the time limits of the client.
        $authorizationEndpoint = HttpsUrl::tryFrom($metadata->string('authorization_endpoint') ?? '');
        $parEndpoint = HttpsUrl::tryFrom($metadata->string('pushed_authorization_request_endpoint') ?? '');
        $tokenEndpoint = HttpsUrl::tryFrom($metadata->string('token_endpoint') ?? '');
        $followsProfile =
            $metadata->isTrue('authorization_response_iss_parameter_supported')
            && \in_array('ES256', $metadata->strings('dpop_signing_alg_values_supported'), strict: true);

        if (!$followsProfile || null === $authorizationEndpoint || null === $parEndpoint || null === $tokenEndpoint) {
            throw new ProviderUnreachable(\sprintf('"%s" does not follow the AT Protocol profile of OAuth.', $issuer));
        }

        return new AuthorizationServer($issuer, $authorizationEndpoint, $parEndpoint, $tokenEndpoint);
    }

    private function fetch(HttpsUrl $url): JsonObject
    {
        try {
            $response = $this->client->request('GET', $url->value);
            if (200 !== $response->getStatusCode()) {
                throw new ProviderUnreachable(\sprintf('"%s" answered %d.', $url, $response->getStatusCode()));
            }

            return new JsonObject($response->toArray());
        } catch (ExceptionInterface $exception) {
            throw new ProviderUnreachable(\sprintf('"%s" gave no metadata.', $url), previous: $exception);
        }
    }
}
