<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\WriteoffItem\Exceptions\BatchProductMismatchException;
use App\Component\WriteoffItem\Exceptions\DuplicateWriteoffItemException;
use App\Component\WriteoffItem\Exceptions\WriteoffNotEditableException;
use App\Entity\WriteoffItem;
use App\Repository\WriteoffItemRepository;

class WriteoffItemValidationService
{
    public function __construct(private readonly WriteoffItemRepository $writeoffItemRepository)
    {
    }

    public function validate(WriteoffItem $data): void
    {
        if ($data->getWriteoff()->getStatus() !== DocStatus::DRAFT) {
            throw new WriteoffNotEditableException('Cannot add items to a writeoff that is not in draft status.');
        }

        if ($data->getBatch()->getProduct() !== $data->getProduct()) {
            throw new BatchProductMismatchException(sprintf(
                'Batch "%s" belongs to product "%s", not "%s".',
                $data->getBatch()->getNumber(),
                $data->getBatch()->getProduct()->getName(),
                $data->getProduct()->getName()
            ));
        }

        $existingItem = $this->writeoffItemRepository->findOneBy([
            'writeoff' => $data->getWriteoff(),
            'batch' => $data->getBatch(),
        ]);

        if ($existingItem !== null) {
            throw new DuplicateWriteoffItemException(sprintf(
                'Batch "%s" is already added to this writeoff.',
                $data->getBatch()->getNumber()
            ));
        }
    }
}
