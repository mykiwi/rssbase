<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The account signed in on this browser, as its provider confirmed it at login. It lives in the session
 * and is never loaded again: the account trace in the database is not a source of authentication.
 */
final readonly class AtProtoUser implements UserInterface
{
    /**
     * @param non-empty-string $did
     * @param bool $canWrite whether the provider granted the write permission, or only the identity
     */
    public function __construct(
        public string $did,
        public string $handle,
        public ?string $avatarUrl = null,
        public bool $canWrite = false,
    ) {}

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->did;
    }
}
