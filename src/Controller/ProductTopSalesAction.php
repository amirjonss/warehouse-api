<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Product\Dtos\TopProductItemDto;
use App\Component\Product\Dtos\TopProductsDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\SaleItemRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

class ProductTopSalesAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleItemRepository $saleItemRepository,
        private RequestStack $requestStack,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): TopProductsDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $from = $request?->query->get('from');
        $to = $request?->query->get('to');
        $limit = (int) ($request?->query->get('limit') ?? 6);

        $rows = $this->saleItemRepository->getTopProducts($from, $to, $limit);

        $items = array_map(
            fn (array $row) => new TopProductItemDto(
                (int) $row['product_id'],
                (string) $row['product_name'],
                (string) $row['quantity'],
                (string) $row['total_usd'],
                (string) $row['total_uzs'],
            ),
            $rows,
        );

        return new TopProductsDto($items);
    }
}
