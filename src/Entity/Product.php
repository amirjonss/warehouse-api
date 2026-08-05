<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Component\Product\Dtos\ProductStockDto;
use App\Component\Product\Enums\Currency;
use App\Component\Product\Enums\UnitCode;
use App\Controller\ProductStockAction;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new GetCollection(
            uriTemplate: '/products/stock',
            controller: ProductStockAction::class,
            output: ProductStockDto::class,
            read: false
        ),
        new Get(),

        new Post(),

        new Patch(),

    ]
)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $sku = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(enumType: Currency::class)]
    private ?Currency $currency = null;

    #[ORM\Column(enumType: UnitCode::class)]
    private ?UnitCode $unit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    private ?string $pack_qty = null;

    #[ORM\Column(enumType: UnitCode::class)]
    private ?UnitCode $packUnit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    private ?string $minStock = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $priceUsd = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private ?string $priceUzs = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getUnit(): ?UnitCode
    {
        return $this->unit;
    }

    public function setUnit(UnitCode $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getPackQty(): ?string
    {
        return $this->pack_qty;
    }

    public function setPackQty(string $pack_qty): static
    {
        $this->pack_qty = $pack_qty;

        return $this;
    }

    public function getPackUnit(): ?UnitCode
    {
        return $this->packUnit;
    }

    public function setPackUnit(UnitCode $packUnit): static
    {
        $this->packUnit = $packUnit;

        return $this;
    }

    public function getMinStock(): ?string
    {
        return $this->minStock;
    }

    public function setMinStock(string $minStock): static
    {
        $this->minStock = $minStock;

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

    public function getPriceUsd(): ?string
    {
        return $this->priceUsd;
    }

    public function setPriceUsd(?string $priceUsd): static
    {
        $this->priceUsd = $priceUsd;

        return $this;
    }

    public function getPriceUzs(): ?string
    {
        return $this->priceUzs;
    }

    public function setPriceUzs(?string $priceUzs): static
    {
        $this->priceUzs = $priceUzs;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
