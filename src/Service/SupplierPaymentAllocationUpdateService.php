<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\SupplierPayment\Exceptions\SupplierPaymentNotEditableException;
use App\Component\SupplierPaymentAllocation\Exceptions\MissingPayRateException;
use App\Component\SupplierPaymentAllocation\SupplierPaymentAllocationCalculator;
use App\Entity\SupplierPaymentAllocation;
use Doctrine\ORM\EntityManagerInterface;

class SupplierPaymentAllocationUpdateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierPaymentAllocationCalculator $supplierPaymentAllocationCalculator,
    ) {
    }

    public function update(SupplierPaymentAllocation $allocation): SupplierPaymentAllocation
    {
        if ($allocation->getSupplierPayment()->getStatus() !== DocStatus::DRAFT) {
            throw new SupplierPaymentNotEditableException(
                'Cannot modify allocations of a supplier payment that is not in draft status.'
            );
        }

        if ($allocation->getCurrency() !== $allocation->getSupplierPayment()->getCurrency()
            && $allocation->getPayRate() === null) {
            throw new MissingPayRateException(
                'payRate is required when the allocation currency differs from the payment currency.'
            );
        }

        $allocation->setAmountClosed(
            $this->supplierPaymentAllocationCalculator->calculateAmountClosed($allocation)
        );

        $this->entityManager->flush();

        return $allocation;
    }
}
