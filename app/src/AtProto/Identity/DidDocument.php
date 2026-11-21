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
        public ?Handle $handle,
        public Pds $pds,
    ) {}

    /**
     * @throws AccountNotFound when the document describes another DID or declares no PDS
     */
    public static function fromJson(Did $did, JsonObject $document): self
    {
        if ($document->string('id') !== $did->value) {
            throw new AccountNotFound(\sprintf('The document of "%s" describes another DID.', $did));
        }

        return new self(
            self::declaredHandle($document),
            self::declaredPds($did, $document) ?? throw new AccountNotFound(\sprintf('"%s" declares no PDS.', $did)),
        );
    }

    /**
     * @return ?Handle the first handle the document claims, or null when it claims none that is valid
     */
    private static function declaredHandle(JsonObject $document): ?Handle
    {
        foreach ($document->strings('alsoKnownAs') as $alias) {
            if (str_starts_with($alias, 'at://')) {
                return Handle::tryFrom(substr($alias, offset: \strlen('at://')));
            }
        }

        return null;
    }

    private static function declaredPds(Did $did, JsonObject $document): ?Pds
    {
        foreach ($document->objects('service') as $service) {
            $isPds =
                \in_array($service->string('id'), ['#atproto_pds', "{$did}#atproto_pds"], strict: true)
                && 'AtprotoPersonalDataServer' === $service->string('type');
            $pds = $isPds ? Pds::tryFrom($service->string('serviceEndpoint') ?? '') : null;

            if (null !== $pds) {
                return $pds;
            }
        }

        return null;
    }
}
