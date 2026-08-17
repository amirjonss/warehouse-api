<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\ReceiptItem;
use App\Service\ReceiptItemUpdateService;
use Symfony\Component\Serializer\SerializerInterface;

class ReceiptItemUpdateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ReceiptItemUpdateService $receiptItemUpdateService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(ReceiptItem $data): ReceiptItem
    {
        return $this->receiptItemUpdateService->update($data);
    }
}
