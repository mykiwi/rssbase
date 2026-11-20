<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseConnectionTest extends KernelTestCase
{
    public function testTheTestEnvironmentQueriesItsSqliteFile(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        static::assertInstanceOf(Connection::class, $connection);
        static::assertStringEndsWith('/var/data_test.db', $connection->getParams()['path'] ?? '');
        static::assertMatchesRegularExpression(
            '/^3\.\d+\.\d+$/',
            (string) $connection->fetchOne('SELECT sqlite_version()'),
        );
    }
}
