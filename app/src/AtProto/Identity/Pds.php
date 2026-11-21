<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

use App\AtProto\HttpsUrl;
use App\AtProto\Identity\Exception\AccountNotFound;

/**
 * The server that hosts the data of an account, named by its https address without a trailing slash.
 *
 * @see https://atproto.com/specs/did#did-documents
 */
final readonly class Pds implements \Stringable
{
    private function __construct(
        public string $value,
    ) {}

    public static function tryFrom(string $endpoint): ?self
    {
        $url = HttpsUrl::tryFrom(rtrim($endpoint, characters: '/'));

        return null === $url ? null : new self($url->value);
    }

    /**
     * @throws AccountNotFound when the value is not the https address of a server
     */
    public static function fromString(string $endpoint): self
    {
        return self::tryFrom($endpoint) ?? throw new AccountNotFound(\sprintf('"%s" is not a PDS.', $endpoint));
    }

    /**
     * @param string $path a path on the server, with its leading slash and its query if any
     */
    public function url(string $path): HttpsUrl
    {
        return HttpsUrl::fromString($this->value . $path);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
