<?php

namespace App\Component\Client\Dtos;

class ClientDebtDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $debtUsd,
        public readonly string $debtUzs,
    ) {
    }
}
