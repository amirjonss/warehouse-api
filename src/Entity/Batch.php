<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Component\Product\Enums\Currency;
use App\Repository\BatchRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BatchRepository::class)]
#[ORM\Table(name: 'batches')]
#[ORM\UniqueConstraint(name: 'uniq_batches_product_number', columns: ['product_id', 'number'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['batch:read']],
    paginationItemsPerPage: 20,
)]
#[Assert\Expression(
    'this.getCurrency() === null || this.getCurrency().value !== "UZS" || this.getRateSell() === "1"',
    message: 'rateSell must be 1 for a UZS batch',
)]
#[ApiFilter(SearchFilter::class, properties: ['product.name' => 'partial'])]
class Batch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['batch:read', 'sale-item:read', 'writeoffs:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['batch:read', 'sale-item:read', 'writeoffs:read'])]
    private ?string $number = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('batch:read')]
    private ?Product $product = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups('batch:read')]
    private ?DateTimeInterface $receivedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[Groups('batch:read')]
    private ?string $initialQty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups('batch:read')]
    private ?string $purchasePrice = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups('batch:read')]
    private ?Currency $currency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[Groups('batch:read')]
    private ?string $rateSell = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('batch:read')]
    private ?Supplier $supplier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups('batch:read')]
    private ?Receipt $receipt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[ApiProperty(writable: false)]
    #[Groups('batch:read')]
    private ?string $remainingQty = '0.000';

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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getReceivedAt(): ?DateTimeInterface
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(DateTimeInterface $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getInitialQty(): ?string
    {
        return $this->initialQty;
    }

    public function setInitialQty(string $initialQty): static
    {
        $this->initialQty = $initialQty;

        return $this;
    }

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(string $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getRateSell(): ?string
    {
        return $this->rateSell;
    }

    public function setRateSell(string $rateSell): static
    {
        $this->rateSell = $rateSell;

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

    public function getReceipt(): ?Receipt
    {
        return $this->receipt;
    }

    public function setReceipt(?Receipt $receipt): static
    {
        $this->receipt = $receipt;

        return $this;
    }

    public function getRemainingQty(): ?string
    {
        return $this->remainingQty;
    }

    public function setRemainingQty(string $remainingQty): static
    {
        $this->remainingQty = $remainingQty;

        return $this;
    }
}
