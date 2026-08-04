<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Sale;
use App\Service\SaleChangeStatusService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class SaleChangeStatusAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleChangeStatusService $saleChangeStatusService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Sale $data): Response
    {
        $sale = $this->saleChangeStatusService->changeStatus($data);

        return $this->responseNormalized($sale);
    }
}
