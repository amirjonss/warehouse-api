<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\InventoryItem;
use App\Service\InventoryItemUpdateService;
use Symfony\Component\Serializer\SerializerInterface;

class InventoryItemUpdateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private InventoryItemUpdateService $inventoryItemUpdateService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(InventoryItem $data): InventoryItem
    {
        // API Platform validates after the controller, and the service flushes inside it — a
        // negative count would reach the CHECK constraint and surface as a 500 instead of a 422.
        $this->validate($data);

        return $this->inventoryItemUpdateService->update($data);
    }
}
