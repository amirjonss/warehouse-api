<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\WriteoffItem\Exceptions\WriteoffNotEditableException;
use App\Entity\Writeoff;
use Doctrine\ORM\EntityManagerInterface;

class WriteoffDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(Writeoff $writeoff): void
    {
        if ($writeoff->getStatus() !== DocStatus::DRAFT) {
            throw new WriteoffNotEditableException('Cannot delete a writeoff that is not in draft status.');
        }

        $this->entityManager->remove($writeoff);
        $this->entityManager->flush();
    }
}
