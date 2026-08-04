<?php

namespace App\Component\Product\Dtos;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Controller\ProductStockAction;
class ProductStockDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $name,
        public readonly string $unit,
        public readonly string $remainingQty,
    ) {
    }
}
