<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\InventoryItem\InventoryItemFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\InventoryItem;
use App\Service\InventoryItemValidationService;
use Symfony\Component\Serializer\SerializerInterface;

class InventoryItemCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private InventoryItemValidationService $inventoryItemValidationService,
        private InventoryItemFactory $inventoryItemFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(InventoryItem $data): InventoryItem
    {
        $this->inventoryItemValidationService->validate($data);

        return $this->inventoryItemFactory->create($data);
    }
}
