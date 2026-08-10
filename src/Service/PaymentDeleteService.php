<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Payment\Exceptions\PaymentStatusTransitionException;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;

class PaymentDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(Payment $payment): void
    {
        if ($payment->getStatus() !== DocStatus::DRAFT) {
            throw new PaymentStatusTransitionException('Cannot delete a payment that is not in draft status.');
        }

        $this->entityManager->remove($payment);
        $this->entityManager->flush();
    }
}
