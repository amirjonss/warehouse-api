<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Component\Product\Enums\Currency;
use App\Controller\PaymentAllocationCreateAction;
use App\Controller\PaymentAllocationDeleteAction;
use App\Controller\PaymentAllocationUpdateAction;
use App\Repository\PaymentAllocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentAllocationRepository::class)]
#[ORM\Table(name: 'payment_allocations')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            controller: PaymentAllocationCreateAction::class,
        ),
        new Patch(
            controller: PaymentAllocationUpdateAction::class,
            denormalizationContext: ['groups' => ['payment-allocation-update:write']],
        ),
        new Delete(
            controller: PaymentAllocationDeleteAction::class,
        ),
    ],
    denormalizationContext: ['groups' => ['payment-allocation:write']],
)]
#[Assert\Expression(
    'this.getPayment() === null || this.getCurrency() === null || this.getCurrency() === this.getPayment().getCurrency() || this.getPayRate() !== null',
    message: 'payRate is required when the allocation currency differs from the payment currency',
)]
class PaymentAllocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'allocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['payment-allocation:write'])]
    private ?Payment $payment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['payment-allocation:write'])]
    private ?Sale $sale = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['payment-allocation:write', 'payment-allocation-update:write'])]
    private ?Currency $currency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $amountClosed = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['payment-allocation:write', 'payment-allocation-update:write'])]
    #[Assert\Positive]
    private ?string $amountSpent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    #[Groups(['payment-allocation:write', 'payment-allocation-update:write'])]
    private ?string $payRate = null;

    #[ORM\Column]
    #[Groups(['payment-allocation:write', 'payment-allocation-update:write'])]
    private ?bool $isRounding = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;

        return $this;
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

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getAmountClosed(): ?string
    {
        return $this->amountClosed;
    }

    public function setAmountClosed(string $amountClosed): static
    {
        $this->amountClosed = $amountClosed;

        return $this;
    }

    public function getAmountSpent(): ?string
    {
        return $this->amountSpent;
    }

    public function setAmountSpent(string $amountSpent): static
    {
        $this->amountSpent = $amountSpent;

        return $this;
    }

    public function getPayRate(): ?string
    {
        return $this->payRate;
    }

    public function setPayRate(?string $payRate): static
    {
        $this->payRate = $payRate;

        return $this;
    }

    public function isRounding(): ?bool
    {
        return $this->isRounding;
    }

    public function setIsRounding(bool $isRounding): static
    {
        $this->isRounding = $isRounding;

        return $this;
    }
}
