<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Component\Product\Enums\Currency;
use App\Controller\SupplierPaymentAllocationCreateAction;
use App\Controller\SupplierPaymentAllocationDeleteAction;
use App\Controller\SupplierPaymentAllocationUpdateAction;
use App\Repository\SupplierPaymentAllocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Which receipt a supplier payment closes, and by how much.
 *
 * amountSpent is money in the payment's currency; currency is the currency of the debt
 * being closed. When they differ the supplier has agreed to take sums for a dollar
 * invoice (or the reverse), payRate is the rate they agreed on, and amountClosed is what
 * the calculator makes of the two.
 */
#[ORM\Entity(repositoryClass: SupplierPaymentAllocationRepository::class)]
#[ORM\Table(name: 'supplier_payment_allocations')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            controller: SupplierPaymentAllocationCreateAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            controller: SupplierPaymentAllocationUpdateAction::class,
            denormalizationContext: ['groups' => ['supplier-payment-allocation-update:write']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            controller: SupplierPaymentAllocationDeleteAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    normalizationContext: ['groups' => ['supplier-payment-allocation:read']],
    denormalizationContext: ['groups' => ['supplier-payment-allocation:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['supplierPayment' => 'exact', 'receipt' => 'exact'])]
class SupplierPaymentAllocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['supplier-payment-allocation:read', 'supplier-payments:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'allocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['supplier-payment-allocation:read', 'supplier-payment-allocation:write'])]
    private ?SupplierPayment $supplierPayment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['supplier-payment-allocation:read', 'supplier-payment-allocation:write', 'supplier-payments:read'])]
    private ?Receipt $receipt = null;

    /** The currency of the debt being closed, which need not be the payment's. */
    #[ORM\Column(enumType: Currency::class)]
    #[Groups([
        'supplier-payment-allocation:read',
        'supplier-payment-allocation:write',
        'supplier-payment-allocation-update:write',
        'supplier-payments:read',
    ])]
    private ?Currency $currency = null;

    /** Money leaving the account, in the payment's currency. */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups([
        'supplier-payment-allocation:read',
        'supplier-payment-allocation:write',
        'supplier-payment-allocation-update:write',
        'supplier-payments:read',
    ])]
    #[Assert\Positive]
    private ?string $amountSpent = null;

    /** Debt closed, in the allocation's currency. Derived, never supplied. */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    #[Groups(['supplier-payment-allocation:read', 'supplier-payments:read'])]
    private ?string $amountClosed = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    #[Groups([
        'supplier-payment-allocation:read',
        'supplier-payment-allocation:write',
        'supplier-payment-allocation-update:write',
        'supplier-payments:read',
    ])]
    #[Assert\Positive]
    private ?string $payRate = null;

    /**
     * The kopeck the agreed rate does not divide into. Signed, computed at posting time
     * by the change-status service, never supplied by a user — which is the whole
     * difference from the old isRounding flag nobody ever read.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    #[Groups(['supplier-payment-allocation:read', 'supplier-payments:read'])]
    private ?string $roundingWriteOff = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupplierPayment(): ?SupplierPayment
    {
        return $this->supplierPayment;
    }

    public function setSupplierPayment(?SupplierPayment $supplierPayment): static
    {
        $this->supplierPayment = $supplierPayment;

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

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

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

    public function getAmountClosed(): ?string
    {
        return $this->amountClosed;
    }

    public function setAmountClosed(string $amountClosed): static
    {
        $this->amountClosed = $amountClosed;

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

    public function getRoundingWriteOff(): ?string
    {
        return $this->roundingWriteOff;
    }

    public function setRoundingWriteOff(string $roundingWriteOff): static
    {
        $this->roundingWriteOff = $roundingWriteOff;

        return $this;
    }

    /** What this allocation actually takes off the receipt, kopeck included. */
    public function getTotalClosed(): string
    {
        return bcadd($this->amountClosed ?? '0', $this->roundingWriteOff ?? '0', 2);
    }
}
