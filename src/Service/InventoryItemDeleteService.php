<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Inventory\InventoryAccess;
use App\Entity\InventoryItem;
use Doctrine\ORM\EntityManagerInterface;

class InventoryItemDeleteService
{
    public function __construct(
        private InventoryAccess $inventoryAccess,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function delete(InventoryItem $inventoryItem): void
    {
        $this->inventoryAccess->assertEditable($inventoryItem->getInventory());

        $this->entityManager->remove($inventoryItem);
        $this->entityManager->flush();
    }
}
