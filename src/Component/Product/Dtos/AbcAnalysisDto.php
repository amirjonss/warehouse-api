<?php

declare(strict_types=1);

namespace App\Component\Product\Dtos;

class AbcAnalysisDto
{
    /**
     * @param AbcItemDto[] $items ranked best to worst
     * @param AbcClassSummaryDto[] $summary always three rows, A/B/C, even when empty
     */
    public function __construct(
        public readonly string $metric,
        public readonly ?string $baseCurrency,
        public readonly string $total,
        public readonly array $items,
        public readonly array $summary,
    ) {
    }
}
