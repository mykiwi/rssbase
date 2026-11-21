<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

use App\AtProto\Identity\Exception\AccountNotFound;

/**
 * The permanent identifier of an account, which stays the same when its handle or its provider changes.
 *
 * @see https://atproto.com/specs/did
 */
final readonly class Did implements \Stringable
{
    private const string SYNTAX = '/^did:[a-z]+:[a-zA-Z0-9._:%-]+$/';

    /**
     * The limit of the specification.
     */
    private const int MAX_LENGTH = 2048;

    /**
     * @param non-empty-string $value
     */
    private function __construct(
        public string $value,
    ) {}

    public static function tryFrom(string $value): ?self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH || 1 !== preg_match(self::SYNTAX, $value)) {
            return null;
        }

        return new self($value);
    }

    /**
     * @throws AccountNotFound when the value is not a DID
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new AccountNotFound(\sprintf('"%s" is not a DID.', $value));
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
