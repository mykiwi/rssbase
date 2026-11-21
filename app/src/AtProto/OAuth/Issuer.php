<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use App\AtProto\HttpsUrl;

/**
 * The name of an authorization server: an https address made of a host alone, as the specification
 * requires.
 *
 * @see https://atproto.com/specs/oauth#authorization-servers
 */
final readonly class Issuer implements \Stringable
{
    private function __construct(
        public string $value,
    ) {}

    public static function tryFrom(string $value): ?self
    {
        return true === HttpsUrl::tryFrom($value)?->isOrigin() ? new self($value) : null;
    }

    public static function fromString(string $value): self
    {
        return (
            self::tryFrom($value) ?? throw new \InvalidArgumentException(\sprintf(
                '"%s" is not the issuer of an authorization server.',
                $value,
            ))
        );
    }

    public function metadataUrl(): HttpsUrl
    {
        return HttpsUrl::fromString("{$this->value}/.well-known/oauth-authorization-server");
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
