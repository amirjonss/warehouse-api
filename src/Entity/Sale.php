<?php

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
use App\Controller\SaleChangeStatusAction;
use App\Controller\SaleCreateAction;
use App\Controller\SaleDeleteAction;
use App\Repository\SaleRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SaleRepository::class)]
#[ORM\Table(name: 'sales')]
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
            controller: SaleCreateAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
        new Post(
            uriTemplate: '/sales/{id}/change_status',
            controller: SaleChangeStatusAction::class,
            denormalizationContext: ['groups' => ['sales-status:write']],
            security: "is_granted('ROLE_SALES')",
        ),
        new Delete(
            controller: SaleDeleteAction::class,
            security: "is_granted('ROLE_SALES')",
        )
    ],
    normalizationContext: ['groups' => ['sales:read']],
    denormalizationContext: ['groups' => ['sales:write']],
    paginationItemsPerPage: 20,
)]
#[ApiFilter(SearchFilter::class, properties: ['customer' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['docDate', 'id'])]
class Sale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['sales:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['sales:read'])]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['sales:write', 'sales:read'])]
    private ?DateTimeInterface $docDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['sales:read'])]
    private ?DateTimeInterface $postedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['sales:write' ,'sales:read'])]
    private ?Client $customer = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups(['sales:read'])]
    private ?string $totalUsd = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['sales:read'])]
    private ?string $totalUzs = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['sales:read'])]
    private ?User $soldBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['sales:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(enumType: DocStatus::class)]
    #[Groups(['sales-status:write', 'sales:read'])]
    private ?DocStatus $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['sales:write', 'sales:read'])]
    private ?string $note = null;

    /**
     * @var Collection<int, SaleItem>
     */
    #[ORM\OneToMany(targetEntity: SaleItem::class, mappedBy: 'sale', orphanRemoval: true)]
    #[Groups(['sales:read'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
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

    public function getCustomer(): ?Client
    {
        return $this->customer;
    }

    public function setCustomer(?Client $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getTotalUsd(): ?string
    {
        return $this->totalUsd;
    }

    public function setTotalUsd(string $totalUsd): static
    {
        $this->totalUsd = $totalUsd;

        return $this;
    }

    public function getTotalUzs(): ?string
    {
        return $this->totalUzs;
    }

    public function setTotalUzs(string $totalUzs): static
    {
        $this->totalUzs = $totalUzs;

        return $this;
    }

    public function getSoldBy(): ?User
    {
        return $this->soldBy;
    }

    public function setSoldBy(?User $soldBy): static
    {
        $this->soldBy = $soldBy;

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

    /**
     * @return Collection<int, SaleItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(SaleItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSale($this);
        }

        return $this;
    }

    public function removeItem(SaleItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getSale() === $this) {
                $item->setSale(null);
            }
        }

        return $this;
    }
}
