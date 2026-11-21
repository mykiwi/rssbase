<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\HttpsUrl;

/**
 * An authorization request that the server accepted: what to keep until the visitor comes back, and
 * where to send them.
 */
final readonly class PushedAuthorization
{
    public function __construct(
        public PendingAuthorization $pending,
        public HttpsUrl $redirectUrl,
    ) {}
}
