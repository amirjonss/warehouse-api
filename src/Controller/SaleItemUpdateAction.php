<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\SaleItem;
use App\Service\SaleItemUpdateService;
use Symfony\Component\Serializer\SerializerInterface;

class SaleItemUpdateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleItemUpdateService $saleItemUpdateService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(SaleItem $data): SaleItem
    {
        return $this->saleItemUpdateService->update($data);
    }
}
