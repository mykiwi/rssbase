<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

/**
 * The endpoints of an authorization server, read from its metadata.
 */
final readonly class AuthorizationServer
{
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $pushedAuthorizationRequestEndpoint,
        public string $tokenEndpoint,
    ) {}
}
