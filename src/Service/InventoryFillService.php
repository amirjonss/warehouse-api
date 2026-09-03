<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Inventory\Exceptions\InventoryFillTooLargeException;
use App\Component\Inventory\InventoryAccess;
use App\Component\InventoryItem\InventoryItemFactory;
use App\Entity\Inventory;
use App\Entity\Product;
use App\Repository\InventoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class InventoryFillService
{
    /**
     * A sheet you cannot see in one document is not a sheet. Rather than paginating a count,
     * we refuse and ask for a category — which is how a warehouse this size gets counted anyway.
     */
    public const MAX_LINES = 500;

    public function __construct(
        private InventoryAccess $inventoryAccess,
        private InventoryRepository $inventoryRepository,
        private InventoryItemFactory $inventoryItemFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function fill(Inventory $inventory, ?int $categoryId, bool $includeZeroStock): Inventory
    {
        $this->inventoryAccess->assertEditable($inventory);

        $categoryId ??= $inventory->getCategory()?->getId();

        $rows = $this->inventoryRepository->findCountableProductRows(
            $inventory,
            $categoryId,
            $includeZeroStock,
            self::MAX_LINES + 1
        );

        if (count($rows) > self::MAX_LINES) {
            throw new InventoryFillTooLargeException(sprintf(
                'Слишком много товаров для одного заполнения (больше %d). Выберите категорию.',
                self::MAX_LINES
            ));
        }

        foreach ($rows as $row) {
            $this->entityManager->persist($this->inventoryItemFactory->createForProduct(
                $inventory,
                $this->entityManager->getReference(Product::class, (int) $row['product_id']),
                $row['expected_qty']
            ));
        }

        $this->entityManager->flush();

        return $inventory;
    }
}
