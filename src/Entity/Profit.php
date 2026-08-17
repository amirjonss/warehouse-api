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
use App\Component\Core\Enums\ProfitEntryType;
use App\Component\Product\Enums\Currency;
use App\Component\Profit\Dtos\ProfitSummaryDto;
use App\Controller\ProfitSummaryAction;
use App\Repository\ProfitRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ProfitRepository::class)]
#[ORM\Table(name: 'profits')]
#[ORM\Index(name: 'idx_profits_sale', columns: ['sale_id'])]
#[ORM\Index(name: 'idx_profits_product_occurred', columns: ['product_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_profits_batch', columns: ['batch_id'])]
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
        new Post(
            uriTemplate: '/profits/summary',
            controller: ProfitSummaryAction::class,
            normalizationContext: ['groups' => ['profit-sum:read']],
            security: "is_granted('ROLE_ADMIN')",
            input: false,
            output: ProfitSummaryDto::class,
            read: false,
            name: 'profitSummary'
        ),
    ],
    normalizationContext: ['groups' => ['profits:read']]
)]
#[ApiFilter(SearchFilter::class, properties: ['product.name' => 'ipartial'])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt', 'id'])]
class Profit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['profits:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['profits:read'])]
    private ?DateTimeInterface $occurredAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['profits:read'])]
    private ?Sale $sale = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['profits:read'])]
    private ?SaleItem $saleItem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['profits:read'])]
    private ?SaleItemAllocation $saleItemAllocation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['profits:read'])]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['profits:read'])]
    private ?Batch $batch = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['profits:read'])]
    private ?string $profit = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['profits:read'])]
    private ?Currency $currency = null;

    #[ORM\Column(enumType: ProfitEntryType::class)]
    #[Groups(['profits:read'])]
    private ?ProfitEntryType $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['profits:read'])]
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

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    public function setSale(?Sale $sale): static
    {
        $this->sale = $sale;

        return $this;
    }

    public function getSaleItem(): ?SaleItem
    {
        return $this->saleItem;
    }

    public function setSaleItem(?SaleItem $saleItem): static
    {
        $this->saleItem = $saleItem;

        return $this;
    }

    public function getSaleItemAllocation(): ?SaleItemAllocation
    {
        return $this->saleItemAllocation;
    }

    public function setSaleItemAllocation(?SaleItemAllocation $saleItemAllocation): static
    {
        $this->saleItemAllocation = $saleItemAllocation;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getBatch(): ?Batch
    {
        return $this->batch;
    }

    public function setBatch(?Batch $batch): static
    {
        $this->batch = $batch;

        return $this;
    }

    public function getProfit(): ?string
    {
        return $this->profit;
    }

    public function setProfit(string $profit): static
    {
        $this->profit = $profit;

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

    public function getType(): ?ProfitEntryType
    {
        return $this->type;
    }

    public function setType(ProfitEntryType $type): static
    {
        $this->type = $type;

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
}
