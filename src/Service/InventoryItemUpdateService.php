<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Inventory\InventoryAccess;
use App\Component\InventoryItem\Exceptions\BatchProductMismatchException;
use App\Entity\InventoryItem;
use Doctrine\ORM\EntityManagerInterface;

class InventoryItemUpdateService
{
    public function __construct(
        private InventoryAccess $inventoryAccess,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Only the counted figure is ever edited here. The expected quantity keeps the value it had
     * when the line was created — re-reading the ledger now would quietly erase whatever moved
     * during the count, which is exactly what the snapshot exists to preserve.
     */
    public function update(InventoryItem $inventoryItem): InventoryItem
    {
        $this->inventoryAccess->assertEditable($inventoryItem->getInventory());

        if ($inventoryItem->getBatch()->getProduct() !== $inventoryItem->getProduct()) {
            throw new BatchProductMismatchException(sprintf(
                'Партия «%s» принадлежит товару «%s», а не «%s».',
                $inventoryItem->getBatch()->getNumber(),
                $inventoryItem->getBatch()->getProduct()->getName(),
                $inventoryItem->getProduct()->getName()
            ));
        }

        $this->entityManager->flush();

        return $inventoryItem;
    }
}
