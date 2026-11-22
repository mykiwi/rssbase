<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RedactAuthorizationCodeTest extends KernelTestCase
{
    public function testTheRequestLoggerLeavesTheAuthorizationCodeOut(): void
    {
        $logger = self::getContainer()->get('monolog.logger.request');
        static::assertInstanceOf(Logger::class, $logger);
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        $logger->info('Matched route "{route}".', [
            'request_uri' => 'http://localhost/oauth/callback?code=code-1&state=s&iss=i',
        ]);

        static::assertSame(
            'http://localhost/oauth/callback?code=[redacted]&state=s&iss=i',
            $handler->getRecords()[0]->context['request_uri'],
        );
    }
}
