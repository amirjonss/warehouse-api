<?php

declare(strict_types=1);

namespace App\Component\User\Dtos;

class RefreshTokenDto
{
    public function __construct(private int $id, private int $iat, private int $tokenVersion = 0)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIat(): int
    {
        return $this->iat;
    }

    public function getTokenVersion(): int
    {
        return $this->tokenVersion;
    }
}
