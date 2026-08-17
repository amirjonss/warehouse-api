<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Payment;
use App\Service\PaymentChangeStatusService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class PaymentChangeStatusAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private PaymentChangeStatusService $paymentChangeStatusService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Payment $data): Response
    {
        $payment = $this->paymentChangeStatusService->changeStatus($data);

        return $this->responseNormalized($payment);
    }
}
