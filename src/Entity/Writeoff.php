<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Component\Core\Enums\DocStatus;
use App\Controller\WriteoffChangeStatusAction;
use App\Controller\WriteoffCreateAction;
use App\Controller\WriteoffDeleteAction;
use App\Repository\WriteoffRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: WriteoffRepository::class)]
#[ORM\Table(name: 'writeoffs')]
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
            controller: WriteoffCreateAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/writeoffs/{id}/change_status',
            controller: WriteoffChangeStatusAction::class,
            denormalizationContext: ['groups' => ['writeoffs-status:write']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            controller: WriteoffDeleteAction::class,
            security: "is_granted('ROLE_ADMIN')",
        )
    ],
    normalizationContext: ['groups' => ['writeoffs:read']],
    denormalizationContext: ['groups' => ['writeoffs:write']],
    paginationItemsPerPage: 20,
)]
#[ApiFilter(OrderFilter::class, properties: ['docDate', 'id'])]
class Writeoff
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['writeoffs:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['writeoffs:read'])]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['writeoffs:write', 'writeoffs:read'])]
    private ?DateTimeInterface $docDate = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['writeoffs:write', 'writeoffs:read'])]
    private ?string $reason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['writeoffs:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['writeoffs:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(enumType: DocStatus::class)]
    #[Groups(['writeoffs-status:write', 'writeoffs:read'])]
    private ?DocStatus $status = null;

    /**
     * @var Collection<int, WriteoffItem>
     */
    #[ORM\OneToMany(targetEntity: WriteoffItem::class, mappedBy: 'writeoff', orphanRemoval: true)]
    #[Groups(['writeoffs:read'])]
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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

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

    /**
     * @return Collection<int, WriteoffItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(WriteoffItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setWriteoff($this);
        }

        return $this;
    }

    public function removeItem(WriteoffItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getWriteoff() === $this) {
                $item->setWriteoff(null);
            }
        }

        return $this;
    }
}
