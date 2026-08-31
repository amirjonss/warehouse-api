<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\MoneyTransfer\Exceptions\MoneyTransferStatusTransitionException;
use App\Entity\MoneyTransfer;
use Doctrine\ORM\EntityManagerInterface;

class MoneyTransferDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(MoneyTransfer $transfer): void
    {
        if ($transfer->getStatus() !== DocStatus::DRAFT) {
            throw new MoneyTransferStatusTransitionException(
                'Cannot delete a money transfer that is not in draft status.'
            );
        }

        $this->entityManager->remove($transfer);
        $this->entityManager->flush();
    }
}
