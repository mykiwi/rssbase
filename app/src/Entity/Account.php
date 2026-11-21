<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The trace of an account that signed in: an operating record that counts the accounts connected at
 * least once. It survives logout. No screen shows it: the operator reads it with "dbal:run-sql".
 */
#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_account_did', columns: ['did'])]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The account stays the same when its handle changes: the DID is its key.
     */
    #[ORM\Column(length: 2048)]
    private string $did;

    #[ORM\Column(length: 253)]
    private string $handle;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastLoginAt;

    public function __construct(string $did, string $handle, \DateTimeImmutable $lastLoginAt)
    {
        $this->did = $did;
        $this->handle = $handle;
        $this->lastLoginAt = $lastLoginAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getLastLoginAt(): \DateTimeImmutable
    {
        return $this->lastLoginAt;
    }
}
