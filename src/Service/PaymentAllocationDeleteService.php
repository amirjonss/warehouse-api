<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\PaymentAllocation\Exceptions\PaymentNotEditableException;
use App\Entity\PaymentAllocation;
use Doctrine\ORM\EntityManagerInterface;

class PaymentAllocationDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(PaymentAllocation $allocation): void
    {
        $payment = $allocation->getPayment();

        if ($payment->getStatus() !== DocStatus::DRAFT) {
            throw new PaymentNotEditableException('Cannot modify allocations of a payment that is not in draft status.');
        }

        $this->entityManager->wrapInTransaction(function () use ($payment, $allocation) {
            $this->entityManager->remove($allocation);
            $payment->removeAllocation($allocation);
        });
    }
}
