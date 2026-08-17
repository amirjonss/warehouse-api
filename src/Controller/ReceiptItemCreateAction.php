<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\ReceiptItem\ReceiptItemFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\ReceiptItem;
use App\Service\ReceiptItemValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class ReceiptItemCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ReceiptItemValidationService $receiptItemValidationService,
        private ReceiptItemFactory $receiptItemFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(ReceiptItem $data): ReceiptItem
    {
        $this->receiptItemValidationService->validate($data);

        return $this->receiptItemFactory->create($data);
    }
}
