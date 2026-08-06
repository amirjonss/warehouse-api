<?php

namespace App\Component\PaymentAllocation;

use App\Component\Product\Enums\Currency;
use App\Entity\PaymentAllocation;

class PaymentAllocationCalculator
{
    public function calculateAmountClosed(PaymentAllocation $allocation): string
    {
        $payment = $allocation->getPayment();

        if ($allocation->getCurrency() === $payment->getCurrency()) {
            return $allocation->getAmountSpent();
        }

        if ($allocation->getCurrency() === Currency::USD) {
            return bcdiv($allocation->getAmountSpent(), $allocation->getPayRate(), 2);
        }

        return bcmul($allocation->getAmountSpent(), $allocation->getPayRate(), 2);
    }
}
