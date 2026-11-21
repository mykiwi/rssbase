<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
final class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * Inserts the account or updates its handle and its date, in one statement dated by the database:
     * no read first, and two logins at the same time cannot create two rows.
     *
     * @param non-empty-string $did
     */
    public function recordLogin(string $did, string $handle): void
    {
        $this
            ->getEntityManager()
            ->getConnection()
            ->executeStatement(
                <<<'SQL'
                    INSERT INTO account (did, handle, last_login_at) VALUES (:did, :handle, CURRENT_TIMESTAMP)
                    ON CONFLICT (did) DO UPDATE SET handle = excluded.handle, last_login_at = excluded.last_login_at
                    SQL,
                ['did' => $did, 'handle' => $handle],
            );
    }
}
