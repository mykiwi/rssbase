<?php

declare(strict_types=1);

namespace App\AtProto;

/**
 * An address of the network. Only https: the HTTP client limits the time of https requests alone, and
 * the specifications require https of every server.
 */
final readonly class HttpsUrl implements \Stringable
{
    private function __construct(
        public string $value,
    ) {}

    public static function tryFrom(string $value): ?self
    {
        $host = parse_url($value, \PHP_URL_HOST);

        return str_starts_with($value, 'https://') && \is_string($host) ? new self($value) : null;
    }

    public static function fromString(string $value): self
    {
        return (
            self::tryFrom($value) ?? throw new \InvalidArgumentException(\sprintf('"%s" is not an https URL.', $value))
        );
    }

    /**
     * @param string $query an encoded query, without its question mark
     */
    public function withQuery(string $query): self
    {
        return new self("{$this->value}?{$query}");
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
