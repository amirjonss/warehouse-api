<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Receipt\ReceiptFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Receipt;
use Symfony\Component\Serializer\SerializerInterface;

class ReceiptCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ReceiptFactory $receiptFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Receipt $data): Receipt
    {
        return $this->receiptFactory->create(
            $this->getUser(),
            $data->getSupplier(),
            $data->getNote() ?? '',
            $data->getDocDate()
        );
    }
}
