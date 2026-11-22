<?php

declare(strict_types=1);

namespace App\AtProto\Identity;

interface DnsTxtRecords
{
    /**
     * @return list<string> the TXT records of the name, none when it does not exist
     */
    public function lookup(string $name): array;
}
