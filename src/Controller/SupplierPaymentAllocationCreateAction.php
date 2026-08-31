<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\SupplierPaymentAllocation\SupplierPaymentAllocationFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\SupplierPaymentAllocation;
use App\Service\SupplierPaymentAllocationValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class SupplierPaymentAllocationCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SupplierPaymentAllocationValidationService $supplierPaymentAllocationValidationService,
        private SupplierPaymentAllocationFactory $supplierPaymentAllocationFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(SupplierPaymentAllocation $data): SupplierPaymentAllocation
    {
        $this->supplierPaymentAllocationValidationService->validate($data);

        return $this->supplierPaymentAllocationFactory->create($data);
    }
}
