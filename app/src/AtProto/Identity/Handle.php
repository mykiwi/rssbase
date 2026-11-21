<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

use App\AtProto\Identity\Exception\AccountNotFound;

/**
 * A handle in its normalized form: lowercase, without "@" or spaces.
 *
 * @see https://atproto.com/specs/handle
 */
final readonly class Handle implements \Stringable
{
    /**
     * Bluesky hosts most accounts: a name typed without a domain is one of its handles.
     */
    private const string DEFAULT_SUFFIX = '.bsky.social';

    private const string SYNTAX = '/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]([a-z0-9-]{0,61}[a-z0-9])?$/';

    /**
     * The top-level domains that the specification disallows. ".test" stays allowed for development.
     */
    private const array DISALLOWED_TLDS = [
        'alt',
        'arpa',
        'example',
        'internal',
        'invalid',
        'local',
        'localhost',
        'onion',
    ];

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws AccountNotFound when the input cannot be a handle
     */
    public static function fromInput(string $input): self
    {
        $handle = strtolower(ltrim(trim($input), characters: '@'));

        if ('' !== $handle && !str_contains($handle, '.')) {
            $handle .= self::DEFAULT_SUFFIX;
        }

        return self::fromString($handle);
    }

    public static function tryFrom(string $value): ?self
    {
        $value = strtolower($value);
        $tld = substr($value, (int) strrpos($value, needle: '.') + 1);

        if (
            \strlen($value) > 253
            || 1 !== preg_match(self::SYNTAX, $value)
            || \in_array($tld, self::DISALLOWED_TLDS, strict: true)
        ) {
            return null;
        }

        return new self($value);
    }

    /**
     * @throws AccountNotFound when the value is not a valid handle
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new AccountNotFound(\sprintf('"%s" is not a valid handle.', $value));
    }

    /**
     * The handle the specification shows for an account whose handle does not resolve back to its DID.
     */
    public static function invalid(): self
    {
        return new self('handle.invalid');
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
