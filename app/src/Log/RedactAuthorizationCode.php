<?php

declare(strict_types=1);

namespace App\Log;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * The router logs the URL of each request, and the OAuth callback carries the authorization code in
 * its query: the code stays out of the logs.
 */
#[AsMonologProcessor(channel: 'request')]
final class RedactAuthorizationCode implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $uri = \is_string($record->context['request_uri'] ?? null) ? $record->context['request_uri'] : '';

        if (!str_contains($uri, 'code=')) {
            return $record;
        }

        return $record->with(context: [
            ...$record->context,
            'request_uri' => preg_replace('/([?&]code=)[^&]*/', replacement: '$1[redacted]', subject: $uri),
        ]);
    }
}
