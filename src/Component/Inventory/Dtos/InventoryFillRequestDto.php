<?php

declare(strict_types=1);

namespace App\Component\Inventory\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * The filter a count sheet is filled from.
 *
 * The category is a plain id rather than an IRI: it is a filter argument, the same shape the
 * report endpoints already take through ReportCriteria.
 */
class InventoryFillRequestDto
{
    public function __construct(
        #[Groups(['inventory-fill:write'])]
        private ?int $category = null,
        /**
         * Exhausted batches are noise on a normal sheet — a year of receipts leaves far more
         * dead batches than live ones — but they are the only way to record goods found that
         * the ledger says are gone.
         */
        #[Groups(['inventory-fill:write'])]
        private bool $includeZeroStock = false,
    ) {
    }

    public function getCategory(): ?int
    {
        return $this->category;
    }

    public function isIncludeZeroStock(): bool
    {
        return $this->includeZeroStock;
    }
}
