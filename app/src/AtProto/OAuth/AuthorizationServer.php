<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\HttpsUrl;

/**
 * The endpoints of an authorization server, read from its metadata.
 */
final readonly class AuthorizationServer
{
    public function __construct(
        public Issuer $issuer,
        public HttpsUrl $authorizationEndpoint,
        public HttpsUrl $pushedAuthorizationRequestEndpoint,
        public HttpsUrl $tokenEndpoint,
    ) {}
}
