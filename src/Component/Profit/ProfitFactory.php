<?php

declare(strict_types=1);

namespace App\Component\Profit;

use App\Component\Core\Enums\ProfitEntryType;
use App\Entity\Profit;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\SaleItemAllocation;
use App\Entity\User;
use DateTime;

class ProfitFactory
{
    public function create(
        ProfitEntryType $type,
        Sale $sale,
        SaleItem $saleItem,
        SaleItemAllocation $allocation,
        string $profit,
        User $createdBy
    ): Profit {
        $profitEntry = new Profit();
        $profitEntry
            ->setOccurredAt(new DateTime())
            ->setSale($sale)
            ->setSaleItem($saleItem)
            ->setSaleItemAllocation($allocation)
            ->setProduct($saleItem->getProduct())
            ->setBatch($allocation->getBatch())
            ->setProfit($profit)
            ->setCurrency($allocation->getCostCurrency())
            ->setType($type)
            ->setCreatedBy($createdBy);

        return $profitEntry;
    }
}
