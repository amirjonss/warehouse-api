<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\InventoryItemCreateAction;
use App\Controller\InventoryItemDeleteAction;
use App\Controller\InventoryItemUpdateAction;
use App\Repository\InventoryItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One counted batch.
 *
 * `expectedQty` is a snapshot of the ledger taken when the line was created, never recomputed:
 * the count is a statement about that moment, and posting applies `actualQty - expectedQty` so
 * that goods which legitimately moved during the count are not resurrected.
 *
 * `actualQty` stays null until somebody actually counts. Null means "not counted", zero means
 * "counted, the shelf is empty" — collapsing the two would write off whole batches silently.
 */
#[ORM\Entity(repositoryClass: InventoryItemRepository::class)]
#[ORM\Table(name: 'inventory_items')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_items_inventory_batch', columns: ['inventory_id', 'batch_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_SALES')"),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(
            controller: InventoryItemCreateAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
        new Patch(
            controller: InventoryItemUpdateAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
        new Delete(
            controller: InventoryItemDeleteAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
    ],
    normalizationContext: ['groups' => ['inventories:read']],
    denormalizationContext: ['groups' => ['inventory-item:write']],
    paginationItemsPerPage: 100,
)]
#[ApiFilter(SearchFilter::class, properties: ['inventory' => 'exact', 'product' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['id'])]
class InventoryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['inventories:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['inventory-item:write'])]
    private ?Inventory $inventory = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inventory-item:write', 'inventories:read'])]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inventory-item:write', 'inventories:read'])]
    private ?Batch $batch = null;

    /**
     * Filled by the factory from the movement journal. Writable would let the counter erase
     * their own discrepancy, so the client cannot set it.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[ApiProperty(writable: false)]
    #[Groups(['inventories:read'])]
    private ?string $expectedQty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3, nullable: true)]
    #[Groups(['inventory-item:write', 'inventories:read'])]
    #[Assert\PositiveOrZero]
    private ?string $actualQty = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(?Inventory $inventory): static
    {
        $this->inventory = $inventory;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getBatch(): ?Batch
    {
        return $this->batch;
    }

    public function setBatch(?Batch $batch): static
    {
        $this->batch = $batch;

        return $this;
    }

    public function getExpectedQty(): ?string
    {
        return $this->expectedQty;
    }

    public function setExpectedQty(string $expectedQty): static
    {
        $this->expectedQty = $expectedQty;

        return $this;
    }

    public function getActualQty(): ?string
    {
        return $this->actualQty;
    }

    public function setActualQty(?string $actualQty): static
    {
        $this->actualQty = $actualQty;

        return $this;
    }

    /**
     * Derived, never stored: a second copy of a value computed from two columns of the same row
     * could only ever drift away from them.
     */
    #[ApiProperty(writable: false)]
    #[Groups(['inventories:read'])]
    public function getDiffQty(): ?string
    {
        if ($this->actualQty === null || $this->expectedQty === null) {
            return null;
        }

        return bcsub($this->actualQty, $this->expectedQty, 3);
    }
}
