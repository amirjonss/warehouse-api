<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Product\Dtos\ProductStockDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ProductRepository;
use App\Repository\StockMovementRepository;
use Symfony\Component\Serializer\SerializerInterface;

class ProductStockAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ProductRepository $productRepository,
        private StockMovementRepository $stockMovementRepository,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): array
    {
        $remainingQtyByProduct = $this->stockMovementRepository->getRemainingQtyByProduct();

        $result = [];
        foreach ($this->productRepository->findAll() as $product) {
            $result[] = new ProductStockDto(
                $product->getId(),
                $product->getSku(),
                $product->getName(),
                $product->getUnit()->value,
                $remainingQtyByProduct[$product->getId()] ?? '0.000'
            );
        }

        return $result;
    }
}
