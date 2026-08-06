<?php

namespace App\Component\PaymentAllocation;

use App\Entity\PaymentAllocation;

class PaymentAllocationFactory
{
    public function __construct(private readonly PaymentAllocationCalculator $paymentAllocationCalculator)
    {
    }

    public function create(PaymentAllocation $data): PaymentAllocation
    {
        $allocation = new PaymentAllocation();
        $allocation
            ->setPayment($data->getPayment())
            ->setSale($data->getSale())
            ->setCurrency($data->getCurrency())
            ->setAmountSpent($data->getAmountSpent())
            ->setPayRate($data->getPayRate())
            ->setIsRounding($data->isRounding() ?? false);

        $allocation->setAmountClosed($this->paymentAllocationCalculator->calculateAmountClosed($allocation));

        $data->getPayment()->addAllocation($allocation);

        return $allocation;
    }
}
