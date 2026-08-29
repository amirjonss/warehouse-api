<?php

declare(strict_types=1);

namespace App\Component\Sale\Dtos;

class SalesAnalysisDto
{
    /**
     * @param SalesAnalysisRowDto[] $rows one per interval, oldest first
     */
    public function __construct(
        public readonly string $interval,
        public readonly string $baseCurrency,
        public readonly array $rows,
        public readonly SalesAnalysisRowDto $total,
    ) {
    }
}
