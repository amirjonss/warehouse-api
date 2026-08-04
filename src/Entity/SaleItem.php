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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SaleItemRepository::class)]
#[ORM\Table(name: 'sale_items')]
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

    /**
     * @var Collection<int, SaleItemAllocation>
     */
    #[ORM\OneToMany(targetEntity: SaleItemAllocation::class, mappedBy: 'saleItem', orphanRemoval: true)]
    private Collection $allocations;

    public function __construct()
    {
        $this->allocations = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, SaleItemAllocation>
     */
    public function getAllocations(): Collection
    {
        return $this->allocations;
    }

    public function addAllocation(SaleItemAllocation $allocation): static
    {
        if (!$this->allocations->contains($allocation)) {
            $this->allocations->add($allocation);
            $allocation->setSaleItem($this);
        }

        return $this;
    }

    public function removeAllocation(SaleItemAllocation $allocation): static
    {
        if ($this->allocations->removeElement($allocation)) {
            if ($allocation->getSaleItem() === $this) {
                $allocation->setSaleItem(null);
            }
        }

        return $this;
    }
}
