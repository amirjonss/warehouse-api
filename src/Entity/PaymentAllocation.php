<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Component\Product\Enums\Currency;
use App\Repository\PaymentAllocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentAllocationRepository::class)]
#[ORM\Table(name: 'payment_allocations')]
#[ApiResource]

class PaymentAllocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'allocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Payment $payment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sale $sale = null;

    #[ORM\Column(enumType: Currency::class)]
    private ?Currency $currency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $amountClosed = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $amountSpent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private ?string $docRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    private ?string $payRate = null;

    #[ORM\Column]
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

    public function getDocRate(): ?string
    {
        return $this->docRate;
    }

    public function setDocRate(string $docRate): static
    {
        $this->docRate = $docRate;

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
