<?php

declare(strict_types=1);

namespace App\Component\SupplierPaymentAllocation;

use App\Component\Core\AmountClosedCalculator;
use App\Entity\SupplierPaymentAllocation;

class SupplierPaymentAllocationCalculator
{
    public function __construct(private readonly AmountClosedCalculator $amountClosedCalculator)
    {
    }

    public function calculateAmountClosed(SupplierPaymentAllocation $allocation): string
    {
        return $this->amountClosedCalculator->calculate(
            $allocation->getCurrency(),
            $allocation->getSupplierPayment()->getCurrency(),
            $allocation->getAmountSpent(),
            $allocation->getPayRate()
        );
    }
}
