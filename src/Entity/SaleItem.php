<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Component\Product\Enums\Currency;
use App\Controller\SaleItemCreateAction;
use App\Controller\SaleItemDeleteAction;
use App\Controller\SaleItemUpdateAction;
use App\Repository\SaleItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SaleItemRepository::class)]
#[ORM\Table(name: 'sale_items')]
#[ORM\UniqueConstraint(name: 'uniq_sale_items_sale_batch', columns: ['sale_id', 'batch_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            controller: SaleItemCreateAction::class,
        ),
        new Patch(
            controller: SaleItemUpdateAction::class,
            denormalizationContext: ['groups' => ['sale-item-update:write']],
        ),
        new Delete(
            controller: SaleItemDeleteAction::class,
        ),
    ],
    denormalizationContext: ['groups' => ['sale:write']],
)]
#[Assert\Expression(
    'this.getCostCurrency() === null || this.getCostCurrency().value !== "UZS" || this.getCostRate() === "1"',
    message: 'costRate must be 1 for a UZS batch',
)]
class SaleItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['sale:write'])]
    private ?Sale $sale = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['sale:write'])]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['sale:write'])]
    private ?Batch $batch = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[Groups(['sale:write', 'sale-item-update:write'])]
    #[Assert\Positive]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups(['sale:write', 'sale-item-update:write'])]
    #[Assert\Positive]
    private ?string $price = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['sale:write', 'sale-item-update:write'])]
    private ?Currency $currency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private ?string $costPrice = null;

    #[ORM\Column(enumType: Currency::class)]
    private ?Currency $costCurrency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private ?string $costRate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    public function setSale(?Sale $sale): static
    {
        $this->sale = $sale;

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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

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

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

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
