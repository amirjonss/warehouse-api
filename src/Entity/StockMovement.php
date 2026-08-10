<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Component\Core\Enums\DocumentType;
use App\Component\Core\Enums\MovementType;
use App\Repository\StockMovementRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Table(name: 'stock_movements')]
#[ORM\Index(name: 'idx_stock_movements_batch', columns: ['batch_id'])]
#[ORM\Index(name: 'idx_stock_movements_product_occurred', columns: ['product_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_stock_movements_doc', columns: ['doc_type', 'doc_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'occurredAt' => new QueryParameter(
                    filter: new DateFilter(),
                    property: 'occurredAt'
                ),
            ]
        ),
        new Get(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['stock-movements:read']],
    paginationItemsPerPage: 20,
)]
#[Assert\Expression(
    'this.getType() === null || this.getQuantity() === null || '
    . '(this.getType().value === "in" && this.getQuantity() > 0) || '
    . '(this.getType().value === "out" && this.getQuantity() < 0) || '
    . '(this.getType().value === "writeoff" && this.getQuantity() < 0) || '
    . '(this.getType().value === "adjust" && this.getQuantity() != 0)',
    message: 'quantity sign must match movement type',
)]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact', 'product.name' => 'ipartial'])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt', 'id'])]
class StockMovement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['stock-movements:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['stock-movements:read'])]
    private ?DateTimeInterface $occurredAt = null;

    #[ORM\Column(enumType: MovementType::class)]
    #[Groups(['stock-movements:read'])]
    private ?MovementType $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['stock-movements:read'])]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['stock-movements:read'])]
    private ?Batch $batch = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[Groups(['stock-movements:read'])]
    private ?string $quantity = null;

    #[ORM\Column(enumType: DocumentType::class)]
    #[Groups(['stock-movements:read'])]
    private ?DocumentType $docType = null;

    #[ORM\Column]
    #[Groups(['stock-movements:read'])]
    private ?int $docId = null;

    #[ORM\Column(length: 255)]
    #[Groups(['stock-movements:read'])]
    private ?string $docNumber = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['stock-movements:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['stock-movements:read'])]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): ?DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(DateTimeInterface $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getType(): ?MovementType
    {
        return $this->type;
    }

    public function setType(MovementType $type): static
    {
        $this->type = $type;

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

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getDocType(): ?DocumentType
    {
        return $this->docType;
    }

    public function setDocType(DocumentType $docType): static
    {
        $this->docType = $docType;

        return $this;
    }

    public function getDocId(): ?int
    {
        return $this->docId;
    }

    public function setDocId(int $docId): static
    {
        $this->docId = $docId;

        return $this;
    }

    public function getDocNumber(): ?string
    {
        return $this->docNumber;
    }

    public function setDocNumber(string $docNumber): static
    {
        $this->docNumber = $docNumber;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
