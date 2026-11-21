<?php

declare(strict_types=1);

namespace App\AtProto\Provider;

/**
 * Where a provider hosts the data of its accounts.
 */
enum Region: string
{
    case Eu = 'eu';
    case Us = 'us';
}
