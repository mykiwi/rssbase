<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

use App\AtProto\Identity\Exception\AccountNotFound;
use App\AtProto\JsonObject;

/**
 * What AT Protocol reads in a DID document: the handle the account claims and the PDS that hosts it.
 *
 * @see https://atproto.com/specs/did#did-documents
 */
final readonly class DidDocument
{
    private function __construct(
        public ?string $handle,
        public string $pds,
    ) {}

    /**
     * @throws AccountNotFound when the document describes another DID or declares no PDS
     */
    public static function fromJson(string $did, JsonObject $document): self
    {
        if ($document->string('id') !== $did) {
            throw new AccountNotFound(\sprintf('The document of "%s" describes another DID.', $did));
        }

        return new self(
            self::declaredHandle($document),
            self::declaredPds($did, $document) ?? throw new AccountNotFound(\sprintf('"%s" declares no PDS.', $did)),
        );
    }

    private static function declaredHandle(JsonObject $document): ?string
    {
        foreach ($document->strings('alsoKnownAs') as $alias) {
            if (str_starts_with($alias, 'at://')) {
                return substr($alias, offset: \strlen('at://'));
            }
        }

        return null;
    }

    private static function declaredPds(string $did, JsonObject $document): ?string
    {
        foreach ($document->objects('service') as $service) {
            $endpoint = $service->string('serviceEndpoint') ?? '';
            $isPds =
                \in_array($service->string('id'), ['#atproto_pds', "{$did}#atproto_pds"], strict: true)
                && 'AtprotoPersonalDataServer' === $service->string('type');

            if ($isPds && str_starts_with($endpoint, 'https://')) {
                return rtrim($endpoint, characters: '/');
            }
        }

        return null;
    }
}
