<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\SupplierPayment\SupplierPaymentFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\SupplierPayment;
use App\Service\SupplierPaymentValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class SupplierPaymentCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SupplierPaymentValidationService $supplierPaymentValidationService,
        private SupplierPaymentFactory $supplierPaymentFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(SupplierPayment $data): SupplierPayment
    {
        $this->supplierPaymentValidationService->validate($data);

        return $this->supplierPaymentFactory->create(
            $this->getUser(),
            $data->getSupplier(),
            $data->getAccount(),
            $data->getAmount(),
            $data->getCurrency(),
            $data->getNote(),
            $data->getDocDate()
        );
    }
}
