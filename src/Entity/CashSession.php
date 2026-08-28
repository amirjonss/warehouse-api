<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Component\Cash\Dtos\CashCloseRequestDto;
use App\Component\Cash\Dtos\CashHandoverRequestDto;
use App\Component\Cash\Dtos\CashOnHandsDto;
use App\Component\Cash\Dtos\CashSessionSummaryDto;
use App\Component\Core\Enums\CashSessionStatus;
use App\Controller\CashHandoverDeclareAction;
use App\Controller\CashOnHandsAction;
use App\Controller\CashSessionCloseAction;
use App\Controller\CashSessionOpenAction;
use App\Controller\CashSessionSummaryAction;
use App\Repository\CashSessionRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CashSessionRepository::class)]
#[ORM\Table(name: 'cash_sessions')]
#[ORM\UniqueConstraint(
    name: 'uniq_cash_sessions_open_user',
    columns: ['user_id'],
    options: ['where' => "((status)::text = 'open'::text)"]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SALES')",
            parameters: [
                'closedAt' => new QueryParameter(
                    filter: new DateFilter(),
                    property: 'closedAt'
                ),
                'openedAt' => new QueryParameter(
                    filter: new DateFilter(),
                    property: 'openedAt'
                ),
            ]
        ),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(
            controller: CashSessionOpenAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
        new Post(
            uriTemplate: '/cash_sessions/{id}/handover',
            controller: CashHandoverDeclareAction::class,
            normalizationContext: ['groups' => ['cash-entry:read']],
            security: "is_granted('ROLE_SALES')",
            input: CashHandoverRequestDto::class,
            output: CashEntry::class,
            deserialize: false,
            validate: false,
            name: 'cashHandover',
        ),
        new Post(
            uriTemplate: '/cash_sessions/{id}/close',
            controller: CashSessionCloseAction::class,
            security: "is_granted('ROLE_ADMIN')",
            input: CashCloseRequestDto::class,
            deserialize: false,
            validate: false,
            name: 'cashSessionClose',
        ),
        new Post(
            uriTemplate: '/cash_sessions/{id}/summary',
            controller: CashSessionSummaryAction::class,
            normalizationContext: ['groups' => ['cash-summary:read']],
            security: "is_granted('ROLE_SALES')",
            input: false,
            output: CashSessionSummaryDto::class,
            deserialize: false,
            validate: false,
            name: 'cashSessionSummary',
        ),
        new Post(
            uriTemplate: '/cash_sessions/on_hands',
            controller: CashOnHandsAction::class,
            normalizationContext: ['groups' => ['cash-on-hands:read']],
            security: "is_granted('ROLE_ADMIN')",
            input: false,
            output: CashOnHandsDto::class,
            read: false,
            deserialize: false,
            validate: false,
            name: 'cashOnHands',
        ),
    ],
    normalizationContext: ['groups' => ['cash-session:read']],
    denormalizationContext: ['groups' => ['cash-session:write']],
    paginationItemsPerPage: 20,
)]
#[ApiFilter(SearchFilter::class, properties: ['user' => 'exact', 'status' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['openedAt', 'closedAt', 'id'])]
class CashSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cash-session:read', 'cash-entry:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['cash-session:read', 'cash-entry:read'])]
    private ?string $number = null;

    /** The seller the money is accounted to. Empty when opening means "for myself". */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cash-session:read', 'cash-session:write'])]
    private ?User $user = null;

    #[ORM\Column(enumType: CashSessionStatus::class)]
    #[Groups(['cash-session:read', 'cash-entry:read'])]
    private ?CashSessionStatus $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['cash-session:read'])]
    private ?DateTimeInterface $openedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cash-session:read'])]
    private ?User $openedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['cash-session:read'])]
    private ?DateTimeInterface $closedAt = null;

    #[ORM\ManyToOne]
    #[Groups(['cash-session:read'])]
    private ?User $closedBy = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    #[Groups(['cash-session:read'])]
    private ?string $balanceUsd = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    #[Groups(['cash-session:read'])]
    private ?string $balanceUzs = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    #[Groups(['cash-session:read'])]
    private ?string $unconfirmedUsd = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    #[Groups(['cash-session:read'])]
    private ?string $unconfirmedUzs = '0.00';

    /** @var Collection<int, CashEntry> */
    #[ORM\OneToMany(targetEntity: CashEntry::class, mappedBy: 'session')]
    private Collection $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): ?CashSessionStatus
    {
        return $this->status;
    }

    public function setStatus(CashSessionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOpenedAt(): ?DateTimeInterface
    {
        return $this->openedAt;
    }

    public function setOpenedAt(DateTimeInterface $openedAt): static
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getOpenedBy(): ?User
    {
        return $this->openedBy;
    }

    public function setOpenedBy(?User $openedBy): static
    {
        $this->openedBy = $openedBy;

        return $this;
    }

    public function getClosedAt(): ?DateTimeInterface
    {
        return $this->closedAt;
    }

    public function setClosedAt(?DateTimeInterface $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function getBalanceUsd(): ?string
    {
        return $this->balanceUsd;
    }

    public function setBalanceUsd(string $balanceUsd): static
    {
        $this->balanceUsd = $balanceUsd;

        return $this;
    }

    public function getBalanceUzs(): ?string
    {
        return $this->balanceUzs;
    }

    public function setBalanceUzs(string $balanceUzs): static
    {
        $this->balanceUzs = $balanceUzs;

        return $this;
    }

    public function getUnconfirmedUsd(): ?string
    {
        return $this->unconfirmedUsd;
    }

    public function setUnconfirmedUsd(string $unconfirmedUsd): static
    {
        $this->unconfirmedUsd = $unconfirmedUsd;

        return $this;
    }

    public function getUnconfirmedUzs(): ?string
    {
        return $this->unconfirmedUzs;
    }

    public function setUnconfirmedUzs(string $unconfirmedUzs): static
    {
        $this->unconfirmedUzs = $unconfirmedUzs;

        return $this;
    }

    /**
     * @return Collection<int, CashEntry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(CashEntry $entry): static
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
            $entry->setSession($this);
        }

        return $this;
    }

    public function removeEntry(CashEntry $entry): static
    {
        $this->entries->removeElement($entry);

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::OPEN;
    }
}
