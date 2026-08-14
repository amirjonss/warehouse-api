<?php

declare(strict_types=1);

namespace App\Component\User\Dtos;

use Symfony\Component\Serializer\Annotation\Groups;

class UserCreatedDto
{
    public function __construct(
        #[Groups(['users:read'])]
        private int $id,

        #[Groups(['users:read'])]
        private string $email,

        #[Groups(['users:read'])]
        private string $password,

        #[Groups(['users:read'])]
        private array $roles,

        #[Groups(['users:read'])]
        private string $firstName,

        #[Groups(['users:read'])]
        private ?string $lastName = null,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }
}
