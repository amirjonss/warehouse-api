<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Sale\SaleFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Sale;
use Symfony\Component\Serializer\SerializerInterface;

class SaleCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private SaleFactory $saleFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Sale $data): Sale
    {
        return $this->saleFactory->create(
            $this->getUser(),
            $data->getCustomer(),
            $data->getNote() ?? '',
            $data->getDocDate()
        );
    }
}
