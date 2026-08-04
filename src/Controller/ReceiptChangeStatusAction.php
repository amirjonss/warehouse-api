<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Receipt;
use App\Service\ReceiptChangeStatusService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class ReceiptChangeStatusAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ReceiptChangeStatusService $receiptChangeStatusService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Receipt $data): Response
    {
        $receipt = $this->receiptChangeStatusService->changeStatus($data);

        return $this->responseNormalized($receipt);
    }
}
