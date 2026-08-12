<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Receipt\Exceptions\ReceiptStatusTransitionException;
use App\Entity\Receipt;
use Doctrine\ORM\EntityManagerInterface;

class ReceiptDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(Receipt $receipt): void
    {
        if ($receipt->getStatus() !== DocStatus::DRAFT) {
            throw new ReceiptStatusTransitionException('Cannot delete a receipt that is not in draft status.');
        }

        $this->entityManager->remove($receipt);
        $this->entityManager->flush();
    }
}
