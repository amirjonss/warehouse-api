<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\SupplierPayment;
use App\Service\SupplierPaymentDeleteService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class SupplierPaymentDeleteAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SupplierPaymentDeleteService $supplierPaymentDeleteService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(SupplierPayment $data): Response
    {
        $this->supplierPaymentDeleteService->delete($data);

        return $this->responseEmpty();
    }
}
