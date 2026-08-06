<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Component\Product\Enums\Currency;
use App\Controller\ReceiptItemCreateAction;
use App\Controller\ReceiptItemDeleteAction;
use App\Controller\ReceiptItemUpdateAction;
use App\Repository\ReceiptItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReceiptItemRepository::class)]
#[ORM\Table(name: 'receipt_items')]
#[ORM\UniqueConstraint(name: 'uniq_receipt_items_receipt_product', columns: ['receipt_id', 'product_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            controller: ReceiptItemCreateAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            controller: ReceiptItemUpdateAction::class,
            denormalizationContext: ['groups' => ['receipt-item-update:write']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            controller: ReceiptItemDeleteAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    denormalizationContext: ['groups' => ['receipt:write']],
)]
#[Assert\Expression(
    'this.getCurrency() === null || this.getCurrency().value !== "UZS" || this.getRate() === "1"',
    message: 'rate must be 1 for a UZS receipt item',
)]
class ReceiptItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['receipt:write'])]
    private ?Receipt $receipt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['receipt:write'])]
    private ?Product $product = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(unique: true, nullable: true)]
    private ?Batch $batch = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[Groups(['receipt:write', 'receipt-item-update:write'])]
    #[Assert\Positive]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups(['receipt:write', 'receipt-item-update:write'])]
    #[Assert\Positive]
    private ?string $price = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['receipt:write', 'receipt-item-update:write'])]
    private ?Currency $currency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    #[Groups(['receipt:write', 'receipt-item-update:write'])]
    #[Assert\Positive]
    private ?string $rate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $total = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRate(): ?string
    {
        return $this->rate;
    }

    public function setRate(string $rate): static
    {
        $this->rate = $rate;

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
}
