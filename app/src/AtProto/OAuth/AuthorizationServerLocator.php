<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\Identity\Exception\ProviderUnreachable;
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
    public function forPds(string $pds): AuthorizationServer
    {
        return $this->forIssuer($this->issuerOf($pds));
    }

    /**
     * @return string the issuer of the authorization server that the PDS names
     *
     * @throws ProviderUnreachable
     */
    public function issuerOf(string $pds): string
    {
        $servers = $this->fetch("{$pds}/.well-known/oauth-protected-resource")->strings('authorization_servers');

        if (1 !== \count($servers) || !self::isOrigin($servers[0])) {
            throw new ProviderUnreachable(\sprintf('"%s" does not name one authorization server.', $pds));
        }

        return $servers[0];
    }

    /**
     * @throws ProviderUnreachable
     */
    public function forIssuer(string $issuer): AuthorizationServer
    {
        $metadata = $this->fetch("{$issuer}/.well-known/oauth-authorization-server");

        // The issuer must be the origin the metadata came from, or another server could speak for it.
        if ($metadata->string('issuer') !== $issuer) {
            throw new ProviderUnreachable(\sprintf('The metadata of "%s" names another issuer.', $issuer));
        }

        $authorizationEndpoint = $metadata->string('authorization_endpoint');
        $parEndpoint = $metadata->string('pushed_authorization_request_endpoint');
        $tokenEndpoint = $metadata->string('token_endpoint');
        $followsProfile =
            $metadata->isTrue('authorization_response_iss_parameter_supported')
            && \in_array('ES256', $metadata->strings('dpop_signing_alg_values_supported'), strict: true);

        if (!$followsProfile || null === $authorizationEndpoint || null === $parEndpoint || null === $tokenEndpoint) {
            throw new ProviderUnreachable(\sprintf('"%s" does not follow the AT Protocol profile of OAuth.', $issuer));
        }

        return new AuthorizationServer($issuer, $authorizationEndpoint, $parEndpoint, $tokenEndpoint);
    }

    private function fetch(string $url): JsonObject
    {
        try {
            $response = $this->client->request('GET', $url);
            if (200 !== $response->getStatusCode()) {
                throw new ProviderUnreachable(\sprintf('"%s" answered %d.', $url, $response->getStatusCode()));
            }

            return new JsonObject($response->toArray());
        } catch (ExceptionInterface $exception) {
            throw new ProviderUnreachable(\sprintf('"%s" gave no metadata.', $url), previous: $exception);
        }
    }

    /**
     * An https URL made of a host alone, as the specification requires of an issuer.
     */
    private static function isOrigin(string $url): bool
    {
        $parts = parse_url($url);

        return (
            \is_array($parts)
            && 'https' === ($parts['scheme'] ?? null)
            && '' !== ($parts['host'] ?? '')
            && [] === array_diff_key($parts, array_flip(['scheme', 'host', 'port']))
        );
    }
}
