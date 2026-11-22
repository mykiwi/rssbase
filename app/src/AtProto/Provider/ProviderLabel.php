<?php

declare(strict_types=1);

namespace App\AtProto\Provider;

/**
 * Why RssBase puts a provider forward.
 */
enum ProviderLabel: string
{
    case Recommended = 'recommended';
    case Popular = 'popular';
}
