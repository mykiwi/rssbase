<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\AtProto\Identity\Handle;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Stores a handle as text and gives it back as a Handle.
 */
final class HandleType extends StringType
{
    public const string NAME = 'handle';

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Handle
    {
        return \is_string($value) ? Handle::fromString($value) : null;
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof Handle ? $value->value : null;
    }
}
