<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Component\Product\Dtos\ProductStockSummaryDto;
use App\Component\Product\Dtos\TopProductsDto;
use App\Component\Product\Enums\Currency;
use App\Component\Product\Enums\UnitCode;
use App\Controller\DeleteAction;
use App\Controller\ProductStockSummaryAction;
use App\Controller\ProductTopSalesAction;
use App\Entity\Interfaces\DeletedAtSettableInterface;
use App\Entity\Traits\DeletedAtAccessorsTrait;
use App\Filter\LowStockFilter;
use App\Repository\ProductRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_SALES')"),
        new Post(
            uriTemplate: '/products/summary',
            controller: ProductStockSummaryAction::class,
            security: "is_granted('ROLE_SALES')",
            input: false,
            output: ProductStockSummaryDto::class,
            read: false,
            name: 'stockSummary',
        ),
        new Post(
            uriTemplate: '/products/top-sales',
            controller: ProductTopSalesAction::class,
            security: "is_granted('ROLE_SALES')",
            input: false,
            output: TopProductsDto::class,
            read: false,
            name: 'topSales',
        ),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(
            controller: DeleteAction::class,
            security: "is_granted('ROLE_ADMIN')",
        )
    ],
    paginationItemsPerPage: 20
)]
#[ApiFilter(RangeFilter::class, properties: ['minStock', 'remainingQty'])]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'ipartial', 'sku' => 'exact', 'category.id' => 'exact']), ]
#[ApiFilter(LowStockFilter::class)]
class Product implements DeletedAtSettableInterface
{
    use DeletedAtAccessorsTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['writeoffs:read', 'profits:read', 'stock-movements:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $sku = null;

    #[ORM\Column(length: 255)]
    #[Groups(['batch:read', 'receipt-item:read', 'receipts:read', 'sales:read', 'writeoffs:read', 'profits:read', 'stock-movements:read'])]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(enumType: Currency::class)]
    private ?Currency $currency = null;

    #[ORM\Column(enumType: UnitCode::class)]
    private ?UnitCode $unit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    private ?string $minStock = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $priceUsd = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private ?string $priceUzs = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    private ?string $remainingQty = '0.000';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $deletedAt = null;

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

    public function getMinStock(): ?string
    {
        return $this->minStock;
    }

    public function setMinStock(string $minStock): static
    {
        $this->minStock = $minStock;

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

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
