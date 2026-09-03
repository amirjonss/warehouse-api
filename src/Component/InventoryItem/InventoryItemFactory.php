<?php

declare(strict_types=1);

namespace App\Component\InventoryItem;

use App\Entity\Batch;
use App\Entity\Inventory;
use App\Entity\InventoryItem;
use App\Entity\Product;
use App\Repository\BatchRepository;

class InventoryItemFactory
{
    public function __construct(private readonly BatchRepository $batchRepository)
    {
    }

    /**
     * The expected quantity is snapshotted here from the movement journal and never taken from
     * the request: a counter who could set it would be able to erase their own discrepancy.
     */
    public function create(InventoryItem $data): InventoryItem
    {
        $inventoryItem = new InventoryItem();
        $inventoryItem
            ->setInventory($data->getInventory())
            ->setProduct($data->getProduct())
            ->setBatch($data->getBatch())
            ->setExpectedQty($this->batchRepository->computeLiveRemainingQty($data->getBatch()))
            ->setActualQty($data->getActualQty());

        $data->getInventory()->addItem($inventoryItem);

        return $inventoryItem;
    }

    /**
     * The fill path already knows the ledger quantity from its own query, so it passes it in
     * instead of making one SUM per batch.
     */
    public function createForBatch(
        Inventory $inventory,
        Product $product,
        Batch $batch,
        string $expectedQty
    ): InventoryItem {
        $inventoryItem = new InventoryItem();
        $inventoryItem
            ->setInventory($inventory)
            ->setProduct($product)
            ->setBatch($batch)
            ->setExpectedQty($expectedQty)
            ->setActualQty(null);

        $inventory->addItem($inventoryItem);

        return $inventoryItem;
    }
}
