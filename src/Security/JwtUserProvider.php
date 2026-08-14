<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\PayloadAwareUserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Reloads the User from the DB on every request (the "main" firewall is stateless, so this
 * runs on every authenticated call, not just login) and rejects the token if the account was
 * soft-deleted or if its tokenVersion no longer matches the one embedded in the JWT payload
 * at issuance — the mechanism that lets password change / delete / logout revoke an already
 * issued access token immediately instead of waiting up to `exp`.
 */
class JwtUserProvider implements UserProviderInterface, PasswordUpgraderInterface, PayloadAwareUserProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function loadUserByIdentifierAndPayload(string $identifier, array $payload): UserInterface
    {
        $user = $this->loadUserByIdentifier($identifier);

        if (($payload['tokenVersion'] ?? null) !== $user->getTokenVersion()) {
            $exception = new UserNotFoundException('Token has been revoked.');
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findActiveOneByEmail($identifier);

        if ($user === null) {
            $exception = new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $refreshed = $this->userRepository->find($user->getId());

        if ($refreshed === null || $refreshed->getDeletedAt() !== null) {
            $exception = new UserNotFoundException(sprintf('User with id %d not found.', $user->getId()));
            $exception->setUserIdentifier((string) $user->getId());

            throw $exception;
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            return;
        }

        $user->setPassword($newHashedPassword);
        $this->entityManager->flush();
    }
}
