<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\SaleItem\Exceptions\DuplicateSaleItemException;
use App\Component\SaleItem\Exceptions\SaleNotEditableException;
use App\Entity\SaleItem;
use App\Repository\SaleItemRepository;

class SaleItemValidationService
{
    public function __construct(private readonly SaleItemRepository $saleItemRepository)
    {
    }

    public function validate(SaleItem $data): void
    {
        if ($data->getSale()->getStatus() !== DocStatus::DRAFT) {
            throw new SaleNotEditableException('Cannot add items to a sale that is not in draft status.');
        }

        $existingItem = $this->saleItemRepository->findOneBy([
            'sale' => $data->getSale(),
            'batch' => $data->getBatch(),
        ]);

        if ($existingItem !== null) {
            throw new DuplicateSaleItemException(sprintf(
                'Batch "%s" is already added to this sale.',
                $data->getBatch()->getNumber()
            ));
        }
    }
}
