<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Component\Core\Enums\DocStatus;
use App\Component\Inventory\Dtos\InventoryFillRequestDto;
use App\Controller\InventoryChangeStatusAction;
use App\Controller\InventoryCreateAction;
use App\Controller\InventoryDeleteAction;
use App\Controller\InventoryFillAction;
use App\Repository\InventoryRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A stocktake: what the ledger says versus what is physically on the shelf.
 *
 * Unlike every other stock document, an inventory does not move goods in one direction — each
 * line carries an absolute counted figure and the difference against the snapshot decides the
 * sign. Posting writes MovementType::ADJUST rows against the batches that were counted and
 * touches no money ledger at all.
 */
#[ORM\Entity(repositoryClass: InventoryRepository::class)]
#[ORM\Table(name: 'inventories')]
#[ORM\Index(name: 'idx_inventories_doc_date', columns: ['doc_date'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SALES')",
            parameters: [
                'docDate' => new QueryParameter(
                    filter: new DateFilter(),
                    property: 'docDate'
                ),
            ]
        ),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(
            controller: InventoryCreateAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
        // Counting is the seller's job, booking the result is the owner's — the same split
        // that guards closing a cash session.
        new Post(
            uriTemplate: '/inventories/{id}/change_status',
            controller: InventoryChangeStatusAction::class,
            denormalizationContext: ['groups' => ['inventories-status:write']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/inventories/{id}/fill',
            controller: InventoryFillAction::class,
            security: "is_granted('ROLE_SALES')",
            input: InventoryFillRequestDto::class,
            output: self::class,
            deserialize: false,
            validate: false,
            name: 'inventoryFill',
        ),
        new Delete(
            controller: InventoryDeleteAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
    ],
    normalizationContext: ['groups' => ['inventories:read']],
    denormalizationContext: ['groups' => ['inventories:write']],
    paginationItemsPerPage: 20,
)]
#[ApiFilter(OrderFilter::class, properties: ['docDate', 'id'])]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['inventories:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['inventories:read'])]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['inventories:write', 'inventories:read'])]
    private ?DateTimeInterface $docDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['inventories:write', 'inventories:read'])]
    private ?string $note = null;

    /**
     * The scope that was actually counted. Without it a later reader cannot tell whether a
     * product missing from the sheet was a genuine zero or simply out of scope.
     */
    #[ORM\ManyToOne]
    #[Groups(['inventories:write', 'inventories:read'])]
    private ?Category $category = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inventories:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['inventories:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['inventories:read'])]
    private ?DateTimeInterface $postedAt = null;

    #[ORM\Column(enumType: DocStatus::class)]
    #[Groups(['inventories-status:write', 'inventories:read'])]
    private ?DocStatus $status = null;

    /**
     * @var Collection<int, InventoryItem>
     */
    #[ORM\OneToMany(targetEntity: InventoryItem::class, mappedBy: 'inventory', orphanRemoval: true)]
    #[Groups(['inventories:read'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getDocDate(): ?DateTimeInterface
    {
        return $this->docDate;
    }

    public function setDocDate(DateTimeInterface $docDate): static
    {
        $this->docDate = $docDate;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPostedAt(): ?DateTimeInterface
    {
        return $this->postedAt;
    }

    public function setPostedAt(?DateTimeInterface $postedAt): static
    {
        $this->postedAt = $postedAt;

        return $this;
    }

    public function getStatus(): ?DocStatus
    {
        return $this->status;
    }

    public function setStatus(DocStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InventoryItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInventory($this);
        }

        return $this;
    }

    public function removeItem(InventoryItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getInventory() === $this) {
                $item->setInventory(null);
            }
        }

        return $this;
    }
}
