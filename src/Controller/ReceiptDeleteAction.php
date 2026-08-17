<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Receipt;
use App\Service\ReceiptDeleteService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class ReceiptDeleteAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ReceiptDeleteService $receiptDeleteService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Receipt $data): Response
    {
        $this->receiptDeleteService->delete($data);

        return $this->responseEmpty();
    }
}
