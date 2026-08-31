<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Component\Core\Enums\DocStatus;
use App\Controller\MoneyTransferChangeStatusAction;
use App\Controller\MoneyTransferCreateAction;
use App\Controller\MoneyTransferDeleteAction;
use App\Repository\MoneyTransferRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Money moving between two company accounts. One document covers both cases the
 * business actually has: depositing cash at the bank, where the currency is the same
 * and the sums must match to the kopeck, and exchanging sums for dollars, where they
 * must not — there the agreed rate explains the difference.
 *
 * Both amounts are physically counted, so both are authoritative; the rate is
 * documentation, and validation only uses it to catch a typo.
 */
#[ORM\Entity(repositoryClass: MoneyTransferRepository::class)]
#[ORM\Table(name: 'money_transfers')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
            parameters: [
                'docDate' => new QueryParameter(
                    filter: new DateFilter(),
                    property: 'docDate'
                ),
            ]
        ),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            controller: MoneyTransferCreateAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/money_transfers/{id}/change_status',
            controller: MoneyTransferChangeStatusAction::class,
            denormalizationContext: ['groups' => ['money-transfers-status:write']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            controller: MoneyTransferDeleteAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    normalizationContext: ['groups' => ['money-transfers:read']],
    denormalizationContext: ['groups' => ['money-transfers:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'fromAccount' => 'exact',
    'toAccount' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['docDate', 'id'])]
class MoneyTransfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['money-transfers:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['money-transfers:read'])]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['money-transfers:read', 'money-transfers:write'])]
    private ?DateTimeInterface $docDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['money-transfers:read'])]
    private ?DateTimeInterface $postedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['money-transfers:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['money-transfers:read', 'money-transfers:write'])]
    private ?CashAccount $fromAccount = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['money-transfers:read', 'money-transfers:write'])]
    private ?CashAccount $toAccount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['money-transfers:read', 'money-transfers:write'])]
    #[Assert\Positive]
    private ?string $amountSent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['money-transfers:read', 'money-transfers:write'])]
    #[Assert\Positive]
    private ?string $amountReceived = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    #[Groups(['money-transfers:read', 'money-transfers:write'])]
    #[Assert\Positive]
    private ?string $rate = null;

    #[ORM\Column(enumType: DocStatus::class)]
    #[Groups(['money-transfers:read', 'money-transfers-status:write'])]
    private ?DocStatus $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['money-transfers:read', 'money-transfers:write'])]
    private ?string $note = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['money-transfers:read'])]
    private ?User $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getDocDate(): ?DateTimeInterface
    {
        return $this->docDate;
    }

    public function setDocDate(DateTimeInterface $docDate): static
    {
        $this->docDate = $docDate;

        return $this;
    }

    public function getPostedAt(): ?DateTimeInterface
    {
        return $this->postedAt;
    }

    public function setPostedAt(?DateTimeInterface $postedAt): static
    {
        $this->postedAt = $postedAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getFromAccount(): ?CashAccount
    {
        return $this->fromAccount;
    }

    public function setFromAccount(?CashAccount $fromAccount): static
    {
        $this->fromAccount = $fromAccount;

        return $this;
    }

    public function getToAccount(): ?CashAccount
    {
        return $this->toAccount;
    }

    public function setToAccount(?CashAccount $toAccount): static
    {
        $this->toAccount = $toAccount;

        return $this;
    }

    public function getAmountSent(): ?string
    {
        return $this->amountSent;
    }

    public function setAmountSent(string $amountSent): static
    {
        $this->amountSent = $amountSent;

        return $this;
    }

    public function getAmountReceived(): ?string
    {
        return $this->amountReceived;
    }

    public function setAmountReceived(string $amountReceived): static
    {
        $this->amountReceived = $amountReceived;

        return $this;
    }

    public function getRate(): ?string
    {
        return $this->rate;
    }

    public function setRate(?string $rate): static
    {
        $this->rate = $rate;

        return $this;
    }

    public function getStatus(): ?DocStatus
    {
        return $this->status;
    }

    public function setStatus(DocStatus $status): static
    {
        $this->status = $status;

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
