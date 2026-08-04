<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\SaleItem;
use App\Service\SaleItemAllocationService;
use App\Service\SaleItemValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class SaleItemCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleItemValidationService $saleItemValidationService,
        private SaleItemAllocationService $saleItemAllocationService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(SaleItem $data): SaleItem
    {
        $this->saleItemValidationService->validate($data);

        return $this->saleItemAllocationService->createWithAllocation($data);
    }
}
