<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\SupplierPaymentAllocation;
use App\Service\SupplierPaymentAllocationUpdateService;
use Symfony\Component\Serializer\SerializerInterface;

class SupplierPaymentAllocationUpdateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SupplierPaymentAllocationUpdateService $supplierPaymentAllocationUpdateService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(SupplierPaymentAllocation $data): SupplierPaymentAllocation
    {
        return $this->supplierPaymentAllocationUpdateService->update($data);
    }
}
