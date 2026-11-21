<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\AtProto\Identity\Did;
use App\AtProto\Identity\Handle;
use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountRepositoryTest extends KernelTestCase
{
    /**
     * An account of this test alone: the browser suite signs in on the same database at the same time.
     */
    private const string DID = 'did:plc:tracetest34567abcdefghijk';
    private const int CLOCK_TICK = 1;

    private AccountRepository $accounts;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->accounts = self::service(AccountRepository::class);
        $this->connection = self::service(Connection::class);
        $this->connection->executeStatement('DELETE FROM account WHERE did = :did', ['did' => self::DID]);
    }

    public function testAFirstLoginRecordsTheHandleAndTheDateOfTheDatabase(): void
    {
        $this->accounts->recordLogin(self::did(), Handle::fromString('alice.fake.test'));

        $account = $this->account();
        static::assertSame('alice.fake.test', $account->getHandle()->value);
        // The clock of SQLite, in UTC: a second may pass between the login and this query.
        static::assertEqualsWithDelta(
            (int) $this->connection->fetchOne("SELECT unixepoch('now')"),
            $account->getLastLoginAt()->getTimestamp(),
            self::CLOCK_TICK,
        );
    }

    public function testALaterLoginMovesTheDateWithoutASecondRow(): void
    {
        $this->accounts->recordLogin(self::did(), Handle::fromString('alice.fake.test'));
        $this->connection->executeStatement("UPDATE account SET last_login_at = '2026-01-01 00:00:00' WHERE did = :did", [
            'did' => self::DID,
        ]);

        $this->accounts->recordLogin(self::did(), Handle::fromString('alice.fake.test'));

        static::assertSame(1, $this->rows());
        static::assertGreaterThan(new \DateTimeImmutable('2026-01-01 00:00:00'), $this->account()->getLastLoginAt());
    }

    public function testANewHandleOnTheSameDidUpdatesTheRow(): void
    {
        $this->accounts->recordLogin(self::did(), Handle::fromString('alice.fake.test'));
        $this->accounts->recordLogin(self::did(), Handle::fromString('alice.example.org'));

        static::assertSame(1, $this->rows());
        static::assertSame('alice.example.org', $this->account()->getHandle()->value);
    }

    private function rows(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account WHERE did = :did', ['did' => self::DID]);
    }

    private function account(): Account
    {
        self::service(EntityManagerInterface::class)->clear();

        return $this->accounts->findOneBy(['did' => self::did()]) ?? throw new \RuntimeException('No account row.');
    }

    private static function did(): Did
    {
        return Did::fromString(self::DID);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        static::assertInstanceOf($id, $service);

        return $service;
    }
}
