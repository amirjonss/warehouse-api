<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\SaleItem;
use App\Service\SaleItemDeleteService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class SaleItemDeleteAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleItemDeleteService $saleItemDeleteService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(SaleItem $data): Response
    {
        $this->saleItemDeleteService->delete($data);

        return $this->responseEmpty();
    }
}
