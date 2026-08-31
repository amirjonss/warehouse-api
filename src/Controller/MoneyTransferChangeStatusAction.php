<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\MoneyTransfer;
use App\Service\MoneyTransferChangeStatusService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class MoneyTransferChangeStatusAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private MoneyTransferChangeStatusService $moneyTransferChangeStatusService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(MoneyTransfer $data): Response
    {
        $transfer = $this->moneyTransferChangeStatusService->changeStatus($data);

        return $this->responseNormalized($transfer);
    }
}
