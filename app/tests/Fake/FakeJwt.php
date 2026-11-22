<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\AtProto\JsonObject;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWS;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;

/**
 * Reads and verifies the ES256 JWTs that clients send to the fake authorization server.
 */
final class FakeJwt
{
    public static function parse(string $compact): ?JWS
    {
        try {
            return new CompactSerializer()->unserialize($compact);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public static function claims(JWS $jws): JsonObject
    {
        return JsonObject::fromJson($jws->getPayload() ?? '');
    }

    public static function verify(JWS $jws, JWK $key): bool
    {
        try {
            return (
                'ES256' === ($jws->getSignature(0)->getProtectedHeader()['alg'] ?? null)
                && new JWSVerifier(new AlgorithmManager([new ES256()]))->verifyWithKey($jws, $key, signature: 0)
            );
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
