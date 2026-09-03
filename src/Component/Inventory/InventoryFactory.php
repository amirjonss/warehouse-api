<?php

declare(strict_types=1);

namespace App\Component\Inventory;

use App\Component\Core\DocumentNumberGenerator;
use App\Component\Core\Enums\DocStatus;
use App\Entity\Category;
use App\Entity\Inventory;
use App\Entity\User;
use App\Repository\InventoryRepository;
use DateTime;

class InventoryFactory
{
    private const NUMBER_PREFIX = 'INV-';
    private const NUMBER_LENGTH = 5;

    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {
    }

    public function create(
        User $createdBy,
        ?string $note = null,
        ?Category $category = null,
        ?DateTime $docDate = null
    ): Inventory {
        $inventory = new Inventory();
        $inventory
            ->setNumber($this->documentNumberGenerator->next(
                self::NUMBER_PREFIX,
                self::NUMBER_LENGTH,
                $this->inventoryRepository->findLastNumber()
            ))
            ->setDocDate($docDate ?? new DateTime())
            ->setNote($note)
            ->setCategory($category)
            ->setCreatedBy($createdBy)
            ->setCreatedAt(new DateTime())
            ->setStatus(DocStatus::DRAFT);

        return $inventory;
    }
}
