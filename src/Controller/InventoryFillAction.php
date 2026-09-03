<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Inventory\Dtos\InventoryFillRequestDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Inventory;
use App\Service\InventoryFillService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @method InventoryFillRequestDto getDtoFromRequest(Request $request, string $dtoClass)
 */
class InventoryFillAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private InventoryFillService $inventoryFillService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Inventory $data, Request $request): Inventory
    {
        $dto = $this->getDtoFromRequest($request, InventoryFillRequestDto::class);
        $this->validate($dto);

        // Returning the entity lets API Platform normalise it with the resource's own read
        // group, so the caller gets the whole sheet back instead of a list of IRIs.
        return $this->inventoryFillService->fill(
            $data,
            $dto->getCategory(),
            $dto->isIncludeZeroStock()
        );
    }
}
