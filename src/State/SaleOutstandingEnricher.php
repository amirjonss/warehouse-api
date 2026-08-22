<?php

declare(strict_types=1);

namespace App\State;

use App\Component\Product\Enums\Currency;
use App\Entity\Sale;
use App\Repository\DebtRepository;

class SaleOutstandingEnricher
{
    public function __construct(private readonly DebtRepository $debtRepository)
    {
    }

    /**
     * @param Sale[] $sales
     */
    public function enrich(array $sales): void
    {
        if ($sales === []) {
            return;
        }

        $ids = [];
        foreach ($sales as $sale) {
            if ($sale->getId() !== null) {
                $ids[] = $sale->getId();
            }
        }

        $balances = $this->debtRepository->getBalancesForSales($ids);

        foreach ($sales as $sale) {
            $saleBalances = $balances[$sale->getId()] ?? [];
            $sale->setOutstandingUsd($saleBalances[Currency::USD->value] ?? '0.00');
            $sale->setOutstandingUzs($saleBalances[Currency::UZS->value] ?? '0.00');
        }
    }
}
