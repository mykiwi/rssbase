<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\JsonObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The "state" of an authorization request: random, and signed with the secret of the application so
 * that it can carry the site the visitor started from. The single callback sends the visitor back to
 * that site, which alone holds their session.
 */
final readonly class SignedState
{
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        #[\SensitiveParameter]
        private string $secret,
    ) {}

    public function issue(Site $site): State
    {
        $payload = Base64Url::encode(json_encode(
            ['site' => $site->value, 'nonce' => bin2hex(random_bytes(16))],
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
        ));

        return new State($payload . '.' . $this->sign($payload));
    }

    /**
     * @return ?Site the site the state was issued for, or null when the state was not issued here
     */
    public function site(State $state): ?Site
    {
        [$payload, $signature] = explode('.', $state->value, limit: 2) + ['', ''];

        if ('' === $payload || !hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $decoded = base64_decode(strtr($payload, from: '-_', to: '+/'), strict: true);

        return Site::tryFrom(JsonObject::fromJson(false === $decoded ? '' : $decoded)->string('site') ?? '');
    }

    private function sign(string $payload): string
    {
        return Base64Url::encode(hash_hmac('sha256', "atproto-state:{$payload}", $this->secret, binary: true));
    }
}
