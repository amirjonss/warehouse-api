<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\WriteoffItem\Exceptions\BatchProductMismatchException;
use App\Component\WriteoffItem\Exceptions\WriteoffNotEditableException;
use App\Entity\WriteoffItem;
use Doctrine\ORM\EntityManagerInterface;

class WriteoffItemUpdateService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function update(WriteoffItem $writeoffItem): WriteoffItem
    {
        if ($writeoffItem->getWriteoff()->getStatus() !== DocStatus::DRAFT) {
            throw new WriteoffNotEditableException('Cannot modify items of a writeoff that is not in draft status.');
        }

        if ($writeoffItem->getBatch()->getProduct() !== $writeoffItem->getProduct()) {
            throw new BatchProductMismatchException(sprintf(
                'Batch "%s" belongs to product "%s", not "%s".',
                $writeoffItem->getBatch()->getNumber(),
                $writeoffItem->getBatch()->getProduct()->getName(),
                $writeoffItem->getProduct()->getName()
            ));
        }

        $this->entityManager->flush();

        return $writeoffItem;
    }
}
