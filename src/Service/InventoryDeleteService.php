<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Inventory\InventoryAccess;
use App\Entity\Inventory;
use Doctrine\ORM\EntityManagerInterface;

class InventoryDeleteService
{
    public function __construct(
        private InventoryAccess $inventoryAccess,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function delete(Inventory $inventory): void
    {
        $this->inventoryAccess->assertEditable($inventory);

        $this->entityManager->remove($inventory);
        $this->entityManager->flush();
    }
}
