<?php

declare(strict_types=1);

namespace App\AtProto\OAuth;

use Symfony\Component\HttpFoundation\Request;

/**
 * One site of RssBase, such as "http://fr.rssbase.localhost:8000": the scheme, the host and the port
 * that a session cookie belongs to.
 */
final readonly class Site implements \Stringable
{
    private function __construct(
        public string $value,
    ) {}

    public static function ofRequest(Request $request): self
    {
        return new self($request->getSchemeAndHttpHost());
    }

    public static function tryFrom(string $value): ?self
    {
        return 1 === preg_match('~^https?://[a-z0-9.\[\]:-]+$~', $value) ? new self($value) : null;
    }

    /**
     * Whether the site is on the machine of the visitor: "localhost" or one of its subdomains.
     */
    public function isLocal(): bool
    {
        $host = (string) parse_url($this->value, \PHP_URL_HOST);

        return 'localhost' === $host || str_ends_with($host, '.localhost');
    }

    /**
     * @param string $path a path on the site, with its leading slash and its query if any
     */
    public function url(string $path): string
    {
        return $this->value . $path;
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
