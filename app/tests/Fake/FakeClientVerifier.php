<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\AtProto\JsonObject;
use Jose\Component\Core\JWK;

/**
 * Checks how the client authenticates: a loopback client by its client_id, a confidential client by
 * an assertion signed with its key.
 */
final readonly class FakeClientVerifier
{
    private const string ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    public function __construct(
        private FakeOAuthStore $store,
    ) {}

    /**
     * @throws FakeOAuthError
     */
    public function checkClient(JsonObject $body): void
    {
        $clientId = $body->text('client_id');
        $valid = str_starts_with($clientId, 'http://localhost?')
            ? self::isLoopbackClient($clientId, $body)
            : $this->isConfidentialClient($clientId, $body);

        if (!$valid) {
            throw new FakeOAuthError('invalid_client');
        }
    }

    private function isConfidentialClient(string $clientId, JsonObject $body): bool
    {
        $key = new JsonObject($this->store->clientKeys[$clientId] ?? []);
        $jws = FakeJwt::parse(
            self::ASSERTION_TYPE === $body->string('client_assertion_type') ? $body->text('client_assertion') : '',
        );
        $claims = null === $jws ? new JsonObject([]) : FakeJwt::claims($jws);

        $checks = [
            null !== $jws
                && new JsonObject($jws->getSignature(0)->getProtectedHeader())->string('kid') === $key->string('kid'),
            null !== $jws && FakeJwt::verify($jws, new JWK($key->all())),
            $clientId === $claims->string('iss'),
            $clientId === $claims->string('sub'),
            FakeAuthorizationServer::ISSUER === $claims->string('aud'),
            abs(time() - ($claims->int('iat') ?? 0)) < 60,
        ];

        return !\in_array(false, $checks, strict: true) && $this->store->useJti($claims->text('jti'));
    }

    /**
     * A loopback client declares its redirect URI and its scope in its client_id. The port of a
     * loopback redirect URI is not matched.
     */
    private static function isLoopbackClient(string $clientId, JsonObject $body): bool
    {
        $declared = [];
        parse_str((string) parse_url($clientId, \PHP_URL_QUERY), $declared);
        $declared = new JsonObject($declared);
        $declaredRedirect = $declared->text('redirect_uri');
        $redirectUri = $body->string('redirect_uri') ?? $declaredRedirect;

        $checks = [
            null === $body->string('client_assertion'),
            \in_array(parse_url($redirectUri, \PHP_URL_HOST), ['127.0.0.1', '[::1]'], strict: true),
            self::withoutPort($declaredRedirect) === self::withoutPort($redirectUri),
            null === $body->string('scope') || $body->string('scope') === $declared->string('scope'),
        ];

        return !\in_array(false, $checks, strict: true);
    }

    private static function withoutPort(string $url): string
    {
        return (string) preg_replace('/:\d+/', replacement: '', subject: $url);
    }
}
