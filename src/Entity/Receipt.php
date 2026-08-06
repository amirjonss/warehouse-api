<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Component\Core\Enums\DocStatus;
use App\Controller\ReceiptCreateAction;
use App\Controller\ReceiptChangeStatusAction;
use App\Repository\ReceiptRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ReceiptRepository::class)]
#[ORM\Table(name: 'receipts')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            controller: ReceiptCreateAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/receipts/{id}/change_status',
            controller: ReceiptChangeStatusAction::class,
            denormalizationContext: ['groups' => ['receipts-status:write']],
            security: "is_granted('ROLE_ADMIN')",
        )
    ],
    denormalizationContext: ['groups' => ['receipts:write']]
)]
class Receipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['receipts:write'])]
    private ?DateTimeInterface $docDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $postedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['receipts:write'])]
    private ?Supplier $supplier = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private ?string $totalUsd = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $totalUzs = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $receivedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(enumType: DocStatus::class)]
    #[Groups(['receipts-status:write'])]
    private ?DocStatus $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['receipts:write'])]
    private ?string $note = null;

    /**
     * @var Collection<int, ReceiptItem>
     */
    #[ORM\OneToMany(targetEntity: ReceiptItem::class, mappedBy: 'receipt', orphanRemoval: true)]
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

    public function getPostedAt(): ?DateTimeInterface
    {
        return $this->postedAt;
    }

    public function setPostedAt(?DateTimeInterface $postedAt): static
    {
        $this->postedAt = $postedAt;

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getTotalUsd(): ?string
    {
        return $this->totalUsd;
    }

    public function setTotalUsd(string $totalUsd): static
    {
        $this->totalUsd = $totalUsd;

        return $this;
    }

    public function getTotalUzs(): ?string
    {
        return $this->totalUzs;
    }

    public function setTotalUzs(string $totalUzs): static
    {
        $this->totalUzs = $totalUzs;

        return $this;
    }

    public function getReceivedBy(): ?User
    {
        return $this->receivedBy;
    }

    public function setReceivedBy(?User $receivedBy): static
    {
        $this->receivedBy = $receivedBy;

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

    public function getStatus(): ?DocStatus
    {
        return $this->status;
    }

    public function setStatus(DocStatus $status): static
    {
        $this->status = $status;

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

    /**
     * @return Collection<int, ReceiptItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ReceiptItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setReceipt($this);
        }

        return $this;
    }

    public function removeItem(ReceiptItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getReceipt() === $this) {
                $item->setReceipt(null);
            }
        }

        return $this;
    }
}
