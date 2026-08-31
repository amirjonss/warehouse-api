<?php

declare(strict_types=1);

namespace App\State;

use App\Component\Product\Enums\Currency;
use App\Entity\Receipt;
use App\Repository\SupplierDebtRepository;

class ReceiptOutstandingEnricher
{
    public function __construct(private readonly SupplierDebtRepository $supplierDebtRepository)
    {
    }

    /**
     * @param Receipt[] $receipts
     */
    public function enrich(array $receipts): void
    {
        if ($receipts === []) {
            return;
        }

        $ids = [];
        foreach ($receipts as $receipt) {
            if ($receipt->getId() !== null) {
                $ids[] = $receipt->getId();
            }
        }

        $balances = $this->supplierDebtRepository->getBalancesForReceipts($ids);

        foreach ($receipts as $receipt) {
            $receiptBalances = $balances[$receipt->getId()] ?? [];
            $receipt->setOutstandingUsd($receiptBalances[Currency::USD->value] ?? '0.00');
            $receipt->setOutstandingUzs($receiptBalances[Currency::UZS->value] ?? '0.00');
        }
    }
}
