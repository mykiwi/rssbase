<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\AtProto\JsonObject;
use Jose\Component\Core\JWK;

/**
 * Checks the DPoP proofs that the AT Protocol profile of OAuth requires on every request.
 */
final readonly class FakeDpopVerifier
{
    public function __construct(
        private FakeOAuthStore $store,
    ) {}

    /**
     * @return string the thumbprint of the key that signed the proof
     *
     * @throws FakeOAuthError
     */
    public function checkProof(?string $proof, string $url): string
    {
        $jws = FakeJwt::parse($proof ?? '') ?? throw new FakeOAuthError('invalid_dpop_proof');
        $header = new JsonObject($jws->getSignature(0)->getProtectedHeader());
        $publicKey = $header->object('jwk') ?? new JsonObject([]);
        $claims = FakeJwt::claims($jws);

        $checks = [
            'dpop+jwt' === $header->string('typ'),
            'EC' === $publicKey->string('kty') && null === $publicKey->string('d'),
            'POST' === $claims->string('htm'),
            $url === $claims->string('htu'),
            abs(time() - ($claims->int('iat') ?? 0)) < 60,
        ];

        if (\in_array(false, $checks, strict: true)) {
            throw new FakeOAuthError('invalid_dpop_proof');
        }

        $key = new JWK($publicKey->all());
        if (!FakeJwt::verify($jws, $key) || !$this->store->useJti($claims->text('jti'))) {
            throw new FakeOAuthError('invalid_dpop_proof');
        }

        if ($claims->string('nonce') !== $this->store->nonce) {
            throw new FakeOAuthError('use_dpop_nonce');
        }

        return $key->thumbprint('sha256');
    }
}
