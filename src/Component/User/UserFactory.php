<?php

declare(strict_types=1);

namespace App\Component\User;

use App\Entity\User;
use DateTime;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFactory
{
    public const ALLOWED_ROLES = ['ROLE_ADMIN', 'ROLE_SALES'];

    public function __construct(private UserPasswordHasherInterface $passwordEncoder)
    {
    }

    public function create(string $email, string $password, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setCreatedAt(new DateTime());
        $user->setPassword($this->passwordEncoder->hashPassword($user, $password));
        $user->setRoles($roles);

        return $user;
    }
}
