<?php

declare(strict_types=1);

namespace App\AtProto\OAuth\Exception;

use App\AtProto\HttpsUrl;

/**
 * The authorization server refused a request with an OAuth error, such as "invalid_grant" for a refresh
 * token it no longer accepts.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 */
final class OAuthError extends \RuntimeException
{
    public function __construct(
        public readonly string $error,
        HttpsUrl $url,
    ) {
        parent::__construct(\sprintf('"%s" answered the OAuth error "%s".', $url, $error));
    }
}
