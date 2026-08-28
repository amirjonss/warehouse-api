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
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Controller\CashHandoverConfirmAction;
use App\Component\Core\Enums\CashEntryKind;
use App\Component\Core\Enums\CashEntryStatus;
use App\Component\Product\Enums\Currency;
use App\Repository\CashEntryRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashEntryRepository::class)]
#[ORM\Table(name: 'cash_entries')]
#[ORM\Index(name: 'idx_cash_entries_session', columns: ['session_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SALES')",
            parameters: [
                'occurredAt' => new QueryParameter(
                    filter: new DateFilter(),
                    property: 'occurredAt'
                ),
            ]
        ),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(
            uriTemplate: '/cash_entries/{id}/confirm',
            controller: CashHandoverConfirmAction::class,
            security: "is_granted('ROLE_ADMIN')",
            deserialize: false,
            validate: false,
            name: 'cashHandoverConfirm',
        ),
    ],
    normalizationContext: ['groups' => ['cash-entry:read']],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'session' => 'exact',
    'session.user' => 'exact',
    'kind' => 'exact',
    'status' => 'exact',
    'currency' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt', 'id'])]
class CashEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cash-entry:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['cash-entry:read'])]
    private ?DateTimeInterface $occurredAt = null;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cash-entry:read'])]
    private ?CashSession $session = null;

    #[ORM\Column(enumType: CashEntryKind::class)]
    #[Groups(['cash-entry:read'])]
    private ?CashEntryKind $kind = null;

    /** Signed: + cash collected, − spent, handed over or short. */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['cash-entry:read'])]
    private ?string $amount = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['cash-entry:read'])]
    private ?Currency $currency = null;

    /** DECLARED only makes sense for HANDOVER: handed over, not yet confirmed by an admin. */
    #[ORM\Column(enumType: CashEntryStatus::class)]
    #[Groups(['cash-entry:read'])]
    private ?CashEntryStatus $status = null;

    #[ORM\ManyToOne]
    #[Groups(['cash-entry:read'])]
    private ?Payment $payment = null;

    #[ORM\ManyToOne]
    #[Groups(['cash-entry:read'])]
    private ?Expense $expense = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cash-entry:read'])]
    private ?string $note = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cash-entry:read'])]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[Groups(['cash-entry:read'])]
    private ?User $confirmedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['cash-entry:read'])]
    private ?DateTimeInterface $confirmedAt = null;

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

    public function getSession(): ?CashSession
    {
        return $this->session;
    }

    public function setSession(?CashSession $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getKind(): ?CashEntryKind
    {
        return $this->kind;
    }

    public function setKind(CashEntryKind $kind): static
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

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStatus(): ?CashEntryStatus
    {
        return $this->status;
    }

    public function setStatus(CashEntryStatus $status): static
    {
        $this->status = $status;

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

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getConfirmedBy(): ?User
    {
        return $this->confirmedBy;
    }

    public function setConfirmedBy(?User $confirmedBy): static
    {
        $this->confirmedBy = $confirmedBy;

        return $this;
    }

    public function getConfirmedAt(): ?DateTimeInterface
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?DateTimeInterface $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }
}
