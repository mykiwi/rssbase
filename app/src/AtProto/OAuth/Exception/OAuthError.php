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

    /**
     * Whether the server no longer accepts the tokens of the session: the refresh token is spent,
     * expired or revoked, or the server no longer knows the client. Any other error, such as too many
     * requests or a proof it could not check, says nothing of the tokens.
     */
    public function endsAccess(): bool
    {
        return \in_array($this->error, ['invalid_grant', 'invalid_client'], strict: true);
    }
}
