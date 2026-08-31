<?php

declare(strict_types=1);

namespace App\Component\SupplierPaymentAllocation;

use App\Entity\SupplierPaymentAllocation;

class SupplierPaymentAllocationFactory
{
    public function __construct(
        private readonly SupplierPaymentAllocationCalculator $supplierPaymentAllocationCalculator,
    ) {
    }

    public function create(SupplierPaymentAllocation $data): SupplierPaymentAllocation
    {
        $allocation = new SupplierPaymentAllocation();
        $allocation
            ->setSupplierPayment($data->getSupplierPayment())
            ->setReceipt($data->getReceipt())
            ->setCurrency($data->getCurrency())
            ->setAmountSpent($data->getAmountSpent())
            ->setPayRate($data->getPayRate())
            ->setRoundingWriteOff('0.00');

        $allocation->setAmountClosed(
            $this->supplierPaymentAllocationCalculator->calculateAmountClosed($allocation)
        );

        $data->getSupplierPayment()->addAllocation($allocation);

        return $allocation;
    }
}
