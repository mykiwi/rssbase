<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\AtProto\JsonObject;

/**
 * What the fake servers read in the forms and the tokens they receive.
 */
final class FakeField
{
    /**
     * @return string the string of the field, or an empty one when the field holds no string
     */
    public static function text(JsonObject $fields, string $name): string
    {
        return $fields->string($name) ?? '';
    }
}
