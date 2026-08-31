<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\MoneyTransfer\MoneyTransferFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\MoneyTransfer;
use App\Service\MoneyTransferValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class MoneyTransferCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private MoneyTransferValidationService $moneyTransferValidationService,
        private MoneyTransferFactory $moneyTransferFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(MoneyTransfer $data): MoneyTransfer
    {
        $this->moneyTransferValidationService->validate($data);

        return $this->moneyTransferFactory->create(
            $this->getUser(),
            $data->getFromAccount(),
            $data->getToAccount(),
            $data->getAmountSent(),
            $data->getAmountReceived(),
            $data->getRate(),
            $data->getNote(),
            $data->getDocDate()
        );
    }
}
