<?php

declare(strict_types=1);

namespace App\Component\User;

use Symfony\Component\String\ByteString;

class PasswordGenerator
{
    public function generate(int $length = 12): string
    {
        return ByteString::fromRandom($length)->toString();
    }
}
