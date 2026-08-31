<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Component\Account\Dtos\OpeningBalanceRequestDto;
use App\Component\Account\Dtos\WalletSummaryDto;
use App\Component\Account\Enums\CashAccountKind;
use App\Component\Product\Enums\Currency;
use App\Controller\CashAccountOpeningBalanceAction;
use App\Controller\WalletSummaryAction;
use App\Repository\CashAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * The company's treasury. A CashSession is one seller's shift — what is in their bag
 * right now; a CashAccount is where the money lives once it has been handed over, plus
 * the card and the bank account that a seller never touches at all.
 *
 * The set of accounts is closed and seeded: (kind, currency) is the identity, which is
 * why nothing here needs a "default account" flag.
 */
#[ORM\Entity(repositoryClass: CashAccountRepository::class)]
#[ORM\Table(name: 'cash_accounts')]
#[ORM\UniqueConstraint(name: 'uniq_cash_accounts_kind_currency', columns: ['kind', 'currency'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            uriTemplate: '/cash_accounts/{id}/opening_balance',
            controller: CashAccountOpeningBalanceAction::class,
            normalizationContext: ['groups' => ['account-entry:read']],
            security: "is_granted('ROLE_ADMIN')",
            input: OpeningBalanceRequestDto::class,
            output: AccountEntry::class,
            deserialize: false,
            validate: false,
            name: 'cashAccountOpeningBalance',
        ),
        new Post(
            uriTemplate: '/cash_accounts/summary',
            controller: WalletSummaryAction::class,
            normalizationContext: ['groups' => ['wallet-summary:read']],
            security: "is_granted('ROLE_ADMIN')",
            input: false,
            output: WalletSummaryDto::class,
            read: false,
            deserialize: false,
            validate: false,
            name: 'walletSummary',
        ),
    ],
    normalizationContext: ['groups' => ['cash-account:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['kind' => 'exact', 'currency' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['id'])]
class CashAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cash-account:read', 'account-entry:read'])]
    private ?int $id = null;

    #[ORM\Column(enumType: CashAccountKind::class)]
    #[Groups(['cash-account:read', 'account-entry:read'])]
    private ?CashAccountKind $kind = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['cash-account:read', 'account-entry:read'])]
    private ?Currency $currency = null;

    #[ORM\Column(length: 255)]
    #[Groups(['cash-account:read', 'account-entry:read'])]
    private ?string $name = null;

    /** Denormalised: the sum of the journal. AccountEntryFactory is its only writer. */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    #[Groups(['cash-account:read'])]
    private ?string $balance = '0.00';

    #[ORM\Column]
    #[Groups(['cash-account:read'])]
    private ?bool $isActive = true;

    /**
     * @var Collection<int, AccountEntry>
     */
    #[ORM\OneToMany(targetEntity: AccountEntry::class, mappedBy: 'account')]
    private Collection $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKind(): ?CashAccountKind
    {
        return $this->kind;
    }

    public function setKind(CashAccountKind $kind): static
    {
        $this->kind = $kind;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getBalance(): ?string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, AccountEntry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(AccountEntry $entry): static
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
            $entry->setAccount($this);
        }

        return $this;
    }
}
