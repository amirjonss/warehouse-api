<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Product\Dtos\ProductStockSummaryDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ProductRepository;
use Symfony\Component\Serializer\SerializerInterface;

class ProductStockSummaryAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ProductRepository $productRepository,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): ProductStockSummaryDto
    {
        $summary = $this->productRepository->getStockSummary();

        return new ProductStockSummaryDto($summary['positions'], $summary['low'], $summary['outOfStock']);
    }
}
