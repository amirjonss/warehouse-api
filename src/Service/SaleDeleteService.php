<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\Sale\Exceptions\SaleStatusTransitionException;
use App\Entity\Sale;
use Doctrine\ORM\EntityManagerInterface;

class SaleDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(Sale $sale): void
    {
        if ($sale->getStatus() !== DocStatus::DRAFT) {
            throw new SaleStatusTransitionException('Cannot delete a sale that is not in draft status.');
        }

        $this->entityManager->remove($sale);
        $this->entityManager->flush();
    }
}
