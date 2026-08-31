<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\SupplierPayment\Exceptions\SupplierPaymentStatusTransitionException;
use App\Entity\SupplierPayment;
use Doctrine\ORM\EntityManagerInterface;

class SupplierPaymentDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(SupplierPayment $payment): void
    {
        if ($payment->getStatus() !== DocStatus::DRAFT) {
            throw new SupplierPaymentStatusTransitionException(
                'Cannot delete a supplier payment that is not in draft status.'
            );
        }

        $this->entityManager->remove($payment);
        $this->entityManager->flush();
    }
}
