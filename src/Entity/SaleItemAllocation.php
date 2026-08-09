<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Component\Product\Enums\Currency;
use App\Repository\SaleItemAllocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SaleItemAllocationRepository::class)]
#[ORM\Table(name: 'sale_item_allocations')]
#[ORM\UniqueConstraint(name: 'uniq_sale_item_allocations_sale_item_batch', columns: ['sale_item_id', 'batch_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_SALES')"),
        new Get(security: "is_granted('ROLE_SALES')"),
    ],
)]
#[Assert\Expression(
    'this.getCostCurrency() === null || this.getCostCurrency().value !== "UZS" || this.getCostRate() === "1"',
    message: 'costRate must be 1 for a UZS batch',
)]
class SaleItemAllocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['sale-item:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'allocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SaleItem $saleItem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['sale-item:read'])]
    private ?Batch $batch = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[ApiProperty(security: "is_granted('ROLE_ADMIN')")]
    private ?string $costPrice = null;

    #[ORM\Column(enumType: Currency::class)]
    #[ApiProperty(security: "is_granted('ROLE_ADMIN')")]
    private ?Currency $costCurrency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[ApiProperty(security: "is_granted('ROLE_ADMIN')")]
    private ?string $costRate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleItem(): ?SaleItem
    {
        return $this->saleItem;
    }

    public function setSaleItem(?SaleItem $saleItem): static
    {
        $this->saleItem = $saleItem;

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

    public function getCostPrice(): ?string
    {
        return $this->costPrice;
    }

    public function setCostPrice(string $costPrice): static
    {
        $this->costPrice = $costPrice;

        return $this;
    }

    public function getCostCurrency(): ?Currency
    {
        return $this->costCurrency;
    }

    public function setCostCurrency(Currency $costCurrency): static
    {
        $this->costCurrency = $costCurrency;

        return $this;
    }

    public function getCostRate(): ?string
    {
        return $this->costRate;
    }

    public function setCostRate(string $costRate): static
    {
        $this->costRate = $costRate;

        return $this;
    }
}
