<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\PaymentAllocation;
use App\Service\PaymentAllocationUpdateService;
use Symfony\Component\Serializer\SerializerInterface;

class PaymentAllocationUpdateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private PaymentAllocationUpdateService $paymentAllocationUpdateService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(PaymentAllocation $data): PaymentAllocation
    {
        return $this->paymentAllocationUpdateService->update($data);
    }
}
