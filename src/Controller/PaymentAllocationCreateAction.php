<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\PaymentAllocation\PaymentAllocationFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\PaymentAllocation;
use App\Service\PaymentAllocationValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class PaymentAllocationCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private PaymentAllocationValidationService $paymentAllocationValidationService,
        private PaymentAllocationFactory $paymentAllocationFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(PaymentAllocation $data): PaymentAllocation
    {
        $this->paymentAllocationValidationService->validate($data);

        return $this->paymentAllocationFactory->create($data);
    }
}
