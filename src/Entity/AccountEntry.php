<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Component\Account\Enums\AccountEntryKind;
use App\Repository\AccountEntryRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * The treasury journal — a fifth ledger next to cash_entries, debts, stock_movements
 * and profits. Append-only: nothing is ever updated or deleted, a reversal is a second
 * row with the opposite sign, so the history stays evidence for both sides.
 *
 * There is no currency column on purpose: an account holds exactly one currency, so the
 * account is the only place it can be stated and the two can never drift apart.
 */
#[ORM\Entity(repositoryClass: AccountEntryRepository::class)]
#[ORM\Table(name: 'account_entries')]
#[ORM\Index(name: 'idx_account_entries_account', columns: ['account_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'occurredAt' => new QueryParameter(
                    filter: new DateFilter(),
                    property: 'occurredAt'
                ),
            ]
        ),
        new Get(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['account-entry:read']],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'account' => 'exact',
    'kind' => 'exact',
    'payment' => 'exact',
    'moneyTransfer' => 'exact',
    'supplierPayment' => 'exact',
    'expense' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt', 'id'])]
class AccountEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['account-entry:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['account-entry:read'])]
    private ?DateTimeInterface $occurredAt = null;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['account-entry:read'])]
    private ?CashAccount $account = null;

    #[ORM\Column(enumType: AccountEntryKind::class)]
    #[Groups(['account-entry:read'])]
    private ?AccountEntryKind $kind = null;

    /** Signed: + money in, − money out. */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['account-entry:read'])]
    private ?string $amount = null;

    /** The shift row this money came out of, when it arrived from a seller. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['account-entry:read'])]
    private ?CashEntry $cashEntry = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['account-entry:read'])]
    private ?Payment $payment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['account-entry:read'])]
    private ?MoneyTransfer $moneyTransfer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['account-entry:read'])]
    private ?SupplierPayment $supplierPayment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['account-entry:read'])]
    private ?Expense $expense = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['account-entry:read'])]
    private ?string $note = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['account-entry:read'])]
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

    public function getAccount(): ?CashAccount
    {
        return $this->account;
    }

    public function setAccount(?CashAccount $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getKind(): ?AccountEntryKind
    {
        return $this->kind;
    }

    public function setKind(AccountEntryKind $kind): static
    {
        $this->kind = $kind;

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

    public function getCashEntry(): ?CashEntry
    {
        return $this->cashEntry;
    }

    public function setCashEntry(?CashEntry $cashEntry): static
    {
        $this->cashEntry = $cashEntry;

        return $this;
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

    public function getMoneyTransfer(): ?MoneyTransfer
    {
        return $this->moneyTransfer;
    }

    public function setMoneyTransfer(?MoneyTransfer $moneyTransfer): static
    {
        $this->moneyTransfer = $moneyTransfer;

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

    public function getExpense(): ?Expense
    {
        return $this->expense;
    }

    public function setExpense(?Expense $expense): static
    {
        $this->expense = $expense;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

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
