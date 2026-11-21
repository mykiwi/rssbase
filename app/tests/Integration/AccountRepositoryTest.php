<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountRepositoryTest extends KernelTestCase
{
    private const string DID = 'did:plc:alice234567abcdefghijklm';
    private const int CLOCK_TICK = 1;

    private AccountRepository $accounts;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->accounts = self::service(AccountRepository::class);
        $this->connection = self::service(Connection::class);
        $this->connection->executeStatement('DELETE FROM account');
    }

    public function testAFirstLoginRecordsTheHandleAndTheDateOfTheDatabase(): void
    {
        $this->accounts->recordLogin(self::DID, handle: 'alice.fake.test');

        $account = $this->account();
        static::assertSame('alice.fake.test', $account->getHandle());
        // The clock of SQLite, in UTC: a second may pass between the login and this query.
        static::assertEqualsWithDelta(
            (int) $this->connection->fetchOne("SELECT unixepoch('now')"),
            $account->getLastLoginAt()->getTimestamp(),
            self::CLOCK_TICK,
        );
    }

    public function testALaterLoginMovesTheDateWithoutASecondRow(): void
    {
        $this->accounts->recordLogin(self::DID, handle: 'alice.fake.test');
        $this->connection->executeStatement("UPDATE account SET last_login_at = '2026-01-01 00:00:00'");

        $this->accounts->recordLogin(self::DID, handle: 'alice.fake.test');

        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account'));
        static::assertGreaterThan(new \DateTimeImmutable('2026-01-01 00:00:00'), $this->account()->getLastLoginAt());
    }

    public function testANewHandleOnTheSameDidUpdatesTheRow(): void
    {
        $this->accounts->recordLogin(self::DID, handle: 'alice.fake.test');
        $this->accounts->recordLogin(self::DID, handle: 'alice.example.org');

        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM account'));
        static::assertSame('alice.example.org', $this->account()->getHandle());
    }

    private function account(): Account
    {
        self::service(EntityManagerInterface::class)->clear();

        return $this->accounts->findOneBy(['did' => self::DID]) ?? throw new \RuntimeException('No account row.');
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
