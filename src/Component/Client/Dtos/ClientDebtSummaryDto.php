<?php

namespace App\Component\Client\Dtos;

class ClientDebtSummaryDto
{
    public function __construct(
        public readonly int $count,
        public readonly string $totalDebtUsd,
        public readonly string $totalDebtUzs,
    ) {
    }
}
