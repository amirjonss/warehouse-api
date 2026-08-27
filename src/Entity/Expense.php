<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Component\Expense\Dtos\ExpenseDailyDto;
use App\Component\Expense\Dtos\ExpenseSummaryDto;
use App\Component\Product\Enums\Currency;
use App\Controller\ExpenseCreateAction;
use App\Controller\ExpenseDailyAction;
use App\Controller\ExpenseDeleteAction;
use App\Controller\ExpenseSummaryAction;
use App\Repository\ExpenseRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Table(name: 'expenses')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SALES')",
            parameters: [
                'docDate' => new QueryParameter(
                    filter: new DateFilter(),
                    property: 'docDate'
                ),
            ]
        ),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(
            controller: ExpenseCreateAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
        new Post(
            uriTemplate: '/expenses/summary',
            controller: ExpenseSummaryAction::class,
            normalizationContext: ['groups' => ['expense-summary:read']],
            security: "is_granted('ROLE_SALES')",
            input: false,
            output: ExpenseSummaryDto::class,
            read: false,
            name: 'expenseSummary',
        ),
        new Post(
            uriTemplate: '/expenses/daily',
            controller: ExpenseDailyAction::class,
            normalizationContext: ['groups' => ['expense-daily:read']],
            security: "is_granted('ROLE_SALES')",
            input: false,
            output: ExpenseDailyDto::class,
            read: false,
            name: 'expenseDaily',
        ),
        new Delete(
            controller: ExpenseDeleteAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
    ],
    normalizationContext: ['groups' => ['expenses:read']],
    denormalizationContext: ['groups' => ['expenses:write']],
)]
#[ApiFilter(OrderFilter::class, properties: ['docDate', 'id'])]
#[ApiFilter(SearchFilter::class, properties: ['description' => 'ipartial', 'currency' => 'exact'])]
class Expense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['expenses:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['expenses:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['expenses:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['expenses:write', 'expenses:read'])]
    private ?DateTimeInterface $docDate = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['expenses:write', 'expenses:read'])]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['expenses:write', 'expenses:read'])]
    #[Assert\Positive]
    private ?string $amount = null;

    /**
     * Валюта расхода: тратят из той наличности, что реально собрана, поэтому
     * доллары и сумы не смешиваются — как и везде в модели.
     */
    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['expenses:write', 'expenses:read'])]
    #[Assert\NotNull]
    private ?Currency $currency = null;

    /**
     * Смена, из наличности которой взяты деньги. Nullable ради расходов, заведённых
     * до появления подотчёта, — у них владельца денег установить уже неоткуда.
     */
    #[ORM\ManyToOne]
    #[ApiProperty(writable: false)]
    #[Groups(['expenses:read'])]
    private ?CashSession $cashSession = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    public function getCashSession(): ?CashSession
    {
        return $this->cashSession;
    }

    public function setCashSession(?CashSession $cashSession): static
    {
        $this->cashSession = $cashSession;

        return $this;
    }
}
