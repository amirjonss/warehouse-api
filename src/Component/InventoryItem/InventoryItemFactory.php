<?php

declare(strict_types=1);

namespace App\Component\InventoryItem;

use App\Entity\Inventory;
use App\Entity\InventoryItem;
use App\Entity\Product;
use App\Repository\ProductRepository;

class InventoryItemFactory
{
    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

    /**
     * The expected quantity is snapshotted here from the movement journal and never taken from
     * the request: a counter who could set it would be able to erase their own discrepancy.
     */
    public function create(InventoryItem $data): InventoryItem
    {
        return $this->build(
            $data->getInventory(),
            $data->getProduct(),
            $this->productRepository->computeLiveRemainingQty($data->getProduct()),
            $data->getActualQty()
        );
    }

    /**
     * The fill path already knows the ledger quantity from its own query, so it passes it in
     * instead of making one SUM per product.
     */
    public function createForProduct(Inventory $inventory, Product $product, string $expectedQty): InventoryItem
    {
        return $this->build($inventory, $product, $expectedQty, null);
    }

    private function build(
        Inventory $inventory,
        Product $product,
        string $expectedQty,
        ?string $actualQty
    ): InventoryItem {
        $inventoryItem = new InventoryItem();
        $inventoryItem
            ->setInventory($inventory)
            ->setProduct($product)
            ->setExpectedQty($expectedQty)
            ->setActualQty($actualQty);

        $inventory->addItem($inventoryItem);

        return $inventoryItem;
    }
}
