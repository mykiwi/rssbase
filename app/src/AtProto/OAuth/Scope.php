<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

/**
 * The permissions a client requests, or the ones a provider granted: names separated by spaces.
 *
 * @see https://atproto.com/specs/permission
 */
final readonly class Scope implements \Stringable
{
    /**
     * Every grant includes it: it confirms the identity of the account.
     */
    private const string IDENTITY = 'atproto';

    public function __construct(
        public string $value,
    ) {}

    public function confirmsIdentity(): bool
    {
        return \in_array(self::IDENTITY, explode(' ', $this->value), strict: true);
    }

    /**
     * A provider that does not know the requested permissions grants only the identity of the account.
     */
    public function grantsWrite(): bool
    {
        return array_any(
            explode(' ', $this->value),
            static fn(string $permission): bool => (
                str_starts_with($permission, 'repo:')
                || 'transition:generic' === $permission
            ),
        );
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
