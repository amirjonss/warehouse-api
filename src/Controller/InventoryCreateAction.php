<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Inventory\InventoryFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Inventory;
use Symfony\Component\Serializer\SerializerInterface;

class InventoryCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private InventoryFactory $inventoryFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Inventory $data): Inventory
    {
        return $this->inventoryFactory->create(
            $this->getUser(),
            $data->getNote(),
            $data->getCategory(),
            $data->getDocDate()
        );
    }
}
