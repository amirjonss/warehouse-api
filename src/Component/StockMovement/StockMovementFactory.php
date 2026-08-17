<?php

declare(strict_types=1);

namespace App\Component\StockMovement;

use App\Component\Core\Enums\DocumentType;
use App\Component\Core\Enums\MovementType;
use App\Entity\Batch;
use App\Entity\Product;
use App\Entity\StockMovement;
use App\Entity\User;
use DateTime;

class StockMovementFactory
{
    public function create(
        MovementType $type,
        Product $product,
        Batch $batch,
        string $quantity,
        DocumentType $docType,
        int $docId,
        string $docNumber,
        User $createdBy
    ): StockMovement {
        $stockMovement = new StockMovement();
        $stockMovement
            ->setOccurredAt(new DateTime())
            ->setType($type)
            ->setProduct($product)
            ->setBatch($batch)
            ->setQuantity($quantity)
            ->setDocType($docType)
            ->setDocId($docId)
            ->setDocNumber($docNumber)
            ->setCreatedBy($createdBy);

        $product->setRemainingQty(bcadd($product->getRemainingQty() ?? '0', $quantity, 3));
        $batch->setRemainingQty(bcadd($batch->getRemainingQty() ?? '0', $quantity, 3));

        return $stockMovement;
    }
}
