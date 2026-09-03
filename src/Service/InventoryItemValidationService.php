<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Inventory\InventoryAccess;
use App\Component\InventoryItem\Exceptions\DuplicateInventoryItemException;
use App\Entity\InventoryItem;
use App\Repository\InventoryItemRepository;

class InventoryItemValidationService
{
    public function __construct(
        private readonly InventoryAccess $inventoryAccess,
        private readonly InventoryItemRepository $inventoryItemRepository,
    ) {
    }

    public function validate(InventoryItem $data): void
    {
        $this->inventoryAccess->assertEditable($data->getInventory());

        $existingItem = $this->inventoryItemRepository->findOneBy([
            'inventory' => $data->getInventory(),
            'product' => $data->getProduct(),
        ]);

        if ($existingItem !== null) {
            throw new DuplicateInventoryItemException(sprintf(
                'Товар «%s» уже есть в этой инвентаризации.',
                $data->getProduct()->getName()
            ));
        }
    }
}
