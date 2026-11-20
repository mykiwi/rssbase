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
        static::assertStringEndsWith(
            '/var/data_test.db',
            (string) $connection->fetchOne("SELECT file FROM pragma_database_list WHERE name = 'main'"),
        );
        static::assertMatchesRegularExpression(
            '/^3\.\d+\.\d+$/',
            (string) $connection->fetchOne('SELECT sqlite_version()'),
        );
    }
}
