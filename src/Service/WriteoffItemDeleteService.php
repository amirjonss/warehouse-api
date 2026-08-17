<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\WriteoffItem\Exceptions\WriteoffNotEditableException;
use App\Entity\WriteoffItem;
use Doctrine\ORM\EntityManagerInterface;

class WriteoffItemDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(WriteoffItem $writeoffItem): void
    {
        if ($writeoffItem->getWriteoff()->getStatus() !== DocStatus::DRAFT) {
            throw new WriteoffNotEditableException('Cannot modify items of a writeoff that is not in draft status.');
        }

        $this->entityManager->remove($writeoffItem);
        $this->entityManager->flush();
    }
}
