<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<AtProtoUser>
 */
final class SessionUserProvider implements UserProviderInterface
{
    public function refreshUser(UserInterface $user): AtProtoUser
    {
        if (!$user instanceof AtProtoUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user "%s".', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return AtProtoUser::class === $class;
    }

    /**
     * Only a login at the provider of the account makes a user: an identifier alone proves nothing.
     */
    public function loadUserByIdentifier(string $identifier): never
    {
        throw new UserNotFoundException();
    }
}
