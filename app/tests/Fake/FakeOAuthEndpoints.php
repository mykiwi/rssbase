<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\AtProto\JsonObject;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The endpoints of the fake authorization server, behind the checks of DPoP and of the client.
 */
final readonly class FakeOAuthEndpoints
{
    public function __construct(
        private FakeOAuthStore $store,
    ) {}

    /**
     * @param array<string, mixed> $options the options of the HTTP request, as the mock client gives them
     */
    public function handle(string $url, array $options): MockResponse
    {
        $body = [];
        parse_str(\is_string($options['body'] ?? null) ? $options['body'] : '', $body);
        $body = new JsonObject($body);

        try {
            $jkt = new FakeDpopVerifier($this->store)->checkProof(self::dpopHeader($options), $url);
            new FakeClientVerifier($this->store)->checkClient($body);

            return match ($url) {
                FakeAuthorizationServer::PAR_ENDPOINT => $this->json($this->pushRequest($body, $jkt), status: 201),
                FakeAuthorizationServer::TOKEN_ENDPOINT => $this->json(new FakeTokenGrants($this->store)->issue(
                    $body,
                    $jkt,
                )),
                default => $this->json(['error' => 'not_found'], status: 404),
            };
        } catch (FakeOAuthError $error) {
            return $this->json(['error' => $error->getMessage()], status: 400);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function pushRequest(JsonObject $body, string $jkt): array
    {
        $checks = [
            'code' === $body->string('response_type'),
            'S256' === $body->string('code_challenge_method'),
            43 === \strlen($body->text('code_challenge')),
            '' !== $body->text('state'),
            '' !== $body->text('redirect_uri'),
            \in_array('atproto', explode(' ', $body->text('scope')), strict: true),
        ];

        if (\in_array(false, $checks, strict: true)) {
            throw new FakeOAuthError('invalid_request');
        }

        $request = array_filter([...$body->all(), 'jkt' => $jkt], \is_string(...));

        return ['request_uri' => $this->store->push($request), 'expires_in' => 300];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function dpopHeader(array $options): ?string
    {
        $headers = new JsonObject(
            \is_array($options['normalized_headers'] ?? null) ? $options['normalized_headers'] : [],
        );
        $line = $headers->strings('dpop')[0] ?? '';

        return '' === $line ? null : trim(substr($line, offset: \strlen('DPoP:')));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body, \JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['content-type' => 'application/json', 'dpop-nonce' => $this->store->nonce],
        ]);
    }
}
