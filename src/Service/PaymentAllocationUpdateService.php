<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\PaymentAllocation\Exceptions\MissingPayRateException;
use App\Component\PaymentAllocation\Exceptions\PaymentNotEditableException;
use App\Component\PaymentAllocation\PaymentAllocationCalculator;
use App\Entity\PaymentAllocation;
use Doctrine\ORM\EntityManagerInterface;

class PaymentAllocationUpdateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentAllocationCalculator $paymentAllocationCalculator,
    ) {
    }

    public function update(PaymentAllocation $allocation): PaymentAllocation
    {
        if ($allocation->getPayment()->getStatus() !== DocStatus::DRAFT) {
            throw new PaymentNotEditableException('Cannot modify allocations of a payment that is not in draft status.');
        }

        if ($allocation->getCurrency() !== $allocation->getPayment()->getCurrency() && $allocation->getPayRate() === null) {
            throw new MissingPayRateException(
                'payRate is required when the allocation currency differs from the payment currency.'
            );
        }

        $allocation->setAmountClosed($this->paymentAllocationCalculator->calculateAmountClosed($allocation));

        $this->entityManager->flush();

        return $allocation;
    }
}
