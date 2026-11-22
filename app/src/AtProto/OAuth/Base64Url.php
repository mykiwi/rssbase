<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

/**
 * @see https://datatracker.ietf.org/doc/html/rfc4648#section-5
 */
final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), from: '+/', to: '-_'), characters: '=');
    }
}
