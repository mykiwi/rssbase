<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RequestContext;

/**
 * How RssBase presents itself to authorization servers.
 *
 * With a private key, it is a confidential client: its client_id is the URL of its metadata document,
 * and it signs an assertion on each request. Sessions can then last up to two years. Without a key, it
 * is a loopback client for development, whose sessions last two weeks.
 *
 * @see https://atproto.com/specs/oauth#clients
 */
final readonly class ClientIdentity
{
    public const string METADATA_PATH = '/oauth-client-metadata.json';
    public const string CALLBACK_PATH = '/oauth/callback';

    private const string ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    /**
     * What the client requests at each login.
     */
    public Scope $scope;

    /**
     * @param string $privateJwk the ES256 private key of a confidential client, as a JWK, or nothing
     */
    public function __construct(
        #[Autowire(env: 'ATPROTO_CLIENT_PRIVATE_JWK')]
        #[\SensitiveParameter]
        private string $privateJwk,
        #[Autowire(param: 'app.atproto.scope')]
        string $scope,
        #[Autowire(param: 'app.name')]
        private string $name,
        private RequestContext $requestContext,
    ) {
        $this->scope = new Scope($scope);
    }

    public function isConfidential(): bool
    {
        return '' !== $this->privateJwk;
    }

    public function clientId(): string
    {
        if ($this->isConfidential()) {
            return $this->siteUrl() . self::METADATA_PATH;
        }

        // The specification requires exactly "http://localhost", with the redirect URI and the scope in its query.
        return 'http://localhost?'
        . http_build_query(
            ['redirect_uri' => $this->redirectUri(), 'scope' => $this->scope->value],
            encoding_type: \PHP_QUERY_RFC3986,
        );
    }

    /**
     * One callback serves both languages: it sends the visitor back to the site they started from.
     * A loopback client must use 127.0.0.1, never localhost, and the port is not checked.
     */
    public function redirectUri(): string
    {
        if ($this->isConfidential()) {
            return $this->siteUrl() . self::CALLBACK_PATH;
        }

        $port = $this->requestContext->getHttpPort();

        return 'http://127.0.0.1' . (80 === $port ? '' : ":{$port}") . self::CALLBACK_PATH;
    }

    /**
     * @return array<string, mixed>
     *
     * @see https://atproto.com/specs/oauth#client-id-metadata-document
     */
    public function metadata(): array
    {
        return [
            'client_id' => $this->clientId(),
            'client_name' => $this->name,
            'client_uri' => $this->siteUrl(),
            'application_type' => 'web',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'redirect_uris' => [$this->redirectUri()],
            'scope' => $this->scope->value,
            'token_endpoint_auth_method' => 'private_key_jwt',
            'token_endpoint_auth_signing_alg' => 'ES256',
            'dpop_bound_access_tokens' => true,
            'jwks' => ['keys' => [$this->publicKey()->all()]],
        ];
    }

    /**
     * Adds what identifies the client to the body of a request to an authorization server.
     */
    public function authenticate(FormBody $body, Issuer $issuer): FormBody
    {
        $body = $body->with(client_id: $this->clientId());

        if (!$this->isConfidential()) {
            return $body;
        }

        return $body->with(client_assertion_type: self::ASSERTION_TYPE, client_assertion: $this->assertion($issuer));
    }

    /**
     * @see https://atproto.com/specs/oauth#confidential-client-authentication
     */
    private function assertion(Issuer $issuer): string
    {
        $now = time();
        $key = $this->privateKey();
        $claims = [
            'iss' => $this->clientId(),
            'sub' => $this->clientId(),
            'aud' => $issuer->value,
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + 60,
        ];

        $jws = new JWSBuilder(new AlgorithmManager([new ES256()]))
            ->withPayload(json_encode($claims, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES))
            ->addSignature($key, ['alg' => 'ES256', 'kid' => $key->get('kid')])
            ->build();

        return new CompactSerializer()->serialize($jws);
    }

    public function publicKey(): JWK
    {
        $public = $this->privateKey()->toPublic()->all();

        return new JWK([
            'kid' => $public['kid'],
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $public['x'],
            'y' => $public['y'],
            'use' => 'sig',
            'alg' => 'ES256',
        ]);
    }

    private function privateKey(): JWK
    {
        $key = JWK::createFromJson($this->privateJwk);

        if ('EC' !== $key->get('kty') || 'P-256' !== $key->get('crv') || !$key->has('d')) {
            throw new \InvalidArgumentException(
                'ATPROTO_CLIENT_PRIVATE_JWK must be an ES256 private key: run "bin/console app:atproto:generate-client-key".',
            );
        }

        // Authorization servers pick the key that verifies an assertion by its "kid".
        return $key->has('kid') ? $key : new JWK([...$key->all(), 'kid' => $key->thumbprint('sha256')]);
    }

    /**
     * The English site, which owns the metadata document and the callback.
     */
    private function siteUrl(): string
    {
        return (
            'https://'
            . (
                $this->requestContext->hasParameter('domain')
                    ? (string) $this->requestContext->getParameter('domain')
                    : $this->requestContext->getHost()
            )
        );
    }
}
