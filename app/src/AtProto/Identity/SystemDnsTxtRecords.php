<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

use App\AtProto\JsonObject;

final class SystemDnsTxtRecords implements DnsTxtRecords
{
    public function lookup(string $name): array
    {
        // A name that does not exist raises a warning: the handle then names no account.
        set_error_handler(static fn(): bool => true);
        try {
            $records = dns_get_record($name, \DNS_TXT);
        } finally {
            restore_error_handler();
        }

        if (false === $records) {
            return [];
        }

        return array_values(array_filter(array_map(static fn(JsonObject $record): ?string => $record->string(
            'txt',
        ), new JsonObject(['records' => $records])->objects('records'))));
    }
}
