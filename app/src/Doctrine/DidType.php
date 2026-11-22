<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\AtProto\Identity\Did;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Stores a DID as text and gives it back as a Did.
 */
final class DidType extends StringType
{
    public const string NAME = 'did';

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Did
    {
        return \is_string($value) ? Did::fromString($value) : null;
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof Did ? $value->value : null;
    }
}
