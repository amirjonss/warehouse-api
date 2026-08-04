<?php

namespace App\Service;

use App\Component\Core\Enums\DocStatus;
use App\Component\ReceiptItem\Exceptions\DuplicateReceiptItemException;
use App\Component\ReceiptItem\Exceptions\ReceiptNotEditableException;
use App\Entity\ReceiptItem;
use App\Repository\ReceiptItemRepository;

class ReceiptItemValidationService
{
    public function __construct(private readonly ReceiptItemRepository $receiptItemRepository)
    {
    }

    public function validate(ReceiptItem $data): void
    {
        if ($data->getReceipt()->getStatus() !== DocStatus::DRAFT) {
            throw new ReceiptNotEditableException('Cannot add items to a receipt that is not in draft status.');
        }

        $existingItem = $this->receiptItemRepository->findOneBy([
            'receipt' => $data->getReceipt(),
            'product' => $data->getProduct(),
        ]);

        if ($existingItem !== null) {
            throw new DuplicateReceiptItemException(sprintf(
                'Product "%s" is already added to this receipt.',
                $data->getProduct()->getName()
            ));
        }
    }
}
