<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\SupplierPaymentAllocation;
use App\Service\SupplierPaymentAllocationDeleteService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class SupplierPaymentAllocationDeleteAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SupplierPaymentAllocationDeleteService $supplierPaymentAllocationDeleteService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(SupplierPaymentAllocation $data): Response
    {
        $this->supplierPaymentAllocationDeleteService->delete($data);

        return $this->responseEmpty();
    }
}
