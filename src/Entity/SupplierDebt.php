<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Component\Core\Enums\DocumentType;
use App\Component\Product\Enums\Currency;
use App\Repository\SupplierDebtRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * What we owe suppliers — the mirror of debts, which is what customers owe us. Posting a
 * receipt opens the obligation, a supplier payment closes it, and nothing is ever
 * updated: a cancellation is a compensating row.
 *
 * A separate table rather than a nullable-everything debts: there both client_id and
 * sale_id are NOT NULL, and receivables are readable by sellers while payables are the
 * owner's business.
 */
#[ORM\Entity(repositoryClass: SupplierDebtRepository::class)]
#[ORM\Table(name: 'supplier_debts')]
#[ORM\Index(name: 'idx_supplier_debts_supplier', columns: ['supplier_id'])]
#[ORM\Index(name: 'idx_supplier_debts_receipt', columns: ['receipt_id'])]
#[ORM\Index(name: 'idx_supplier_debts_payment', columns: ['supplier_payment_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['receipt' => 'exact', 'supplier' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt', 'id'])]
class SupplierDebt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $occurredAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Supplier $supplier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Receipt $receipt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?SupplierPayment $supplierPayment = null;

    /** Signed: + what the receipt opened, − what a payment closed. */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(enumType: Currency::class)]
    private ?Currency $currency = null;

    #[ORM\Column(enumType: DocumentType::class)]
    private ?DocumentType $docType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): ?DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(DateTimeInterface $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getReceipt(): ?Receipt
    {
        return $this->receipt;
    }

    public function setReceipt(Receipt $receipt): static
    {
        $this->receipt = $receipt;

        return $this;
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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

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

    public function getDocType(): ?DocumentType
    {
        return $this->docType;
    }

    public function setDocType(DocumentType $docType): static
    {
        $this->docType = $docType;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
