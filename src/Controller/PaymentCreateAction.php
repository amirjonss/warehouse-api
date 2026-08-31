<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Payment\PaymentFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Payment;
use App\Service\PaymentValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class PaymentCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private PaymentValidationService $paymentValidationService,
        private PaymentFactory $paymentFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Payment $data): Payment
    {
        $this->paymentValidationService->validate($data);

        return $this->paymentFactory->create(
            $this->getUser(),
            $data->getClient(),
            $data->getAmount(),
            $data->getCurrency(),
            $data->getMethod(),
            $data->getRateKind(),
            $data->getRate(),
            $data->getNote() ?? '',
            $data->getDocDate()
        );
    }
}
