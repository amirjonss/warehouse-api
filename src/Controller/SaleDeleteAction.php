<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Sale;
use App\Service\SaleDeleteService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class SaleDeleteAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleDeleteService $saleDeleteService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Sale $data): Response
    {
        $this->saleDeleteService->delete($data);

        return $this->responseEmpty();
    }
}
