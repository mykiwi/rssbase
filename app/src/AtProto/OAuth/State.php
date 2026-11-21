<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

/**
 * The "state" of an authorization request, which the provider sends back with its answer.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-10.12
 */
final readonly class State implements \Stringable
{
    public function __construct(
        public string $value,
    ) {}

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
