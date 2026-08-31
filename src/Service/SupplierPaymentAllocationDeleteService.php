<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\SupplierPayment\Exceptions\SupplierPaymentNotEditableException;
use App\Entity\SupplierPaymentAllocation;
use Doctrine\ORM\EntityManagerInterface;

class SupplierPaymentAllocationDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(SupplierPaymentAllocation $allocation): void
    {
        $payment = $allocation->getSupplierPayment();

        if ($payment->getStatus() !== DocStatus::DRAFT) {
            throw new SupplierPaymentNotEditableException(
                'Cannot modify allocations of a supplier payment that is not in draft status.'
            );
        }

        $this->entityManager->wrapInTransaction(function () use ($payment, $allocation) {
            $this->entityManager->remove($allocation);
            $payment->removeAllocation($allocation);
        });
    }
}
