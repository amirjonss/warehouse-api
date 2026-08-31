<?php

declare(strict_types=1);

namespace App\Component\PaymentAllocation;

use App\Component\Core\AmountClosedCalculator;
use App\Entity\PaymentAllocation;

class PaymentAllocationCalculator
{
    public function __construct(private readonly AmountClosedCalculator $amountClosedCalculator)
    {
    }

    public function calculateAmountClosed(PaymentAllocation $allocation): string
    {
        return $this->amountClosedCalculator->calculate(
            $allocation->getCurrency(),
            $allocation->getPayment()->getCurrency(),
            $allocation->getAmountSpent(),
            $allocation->getPayRate()
        );
    }
}
