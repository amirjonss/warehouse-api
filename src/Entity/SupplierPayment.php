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
use App\Component\Product\Enums\Currency;
use App\Controller\SupplierPaymentAutoAllocateAction;
use App\Controller\SupplierPaymentChangeStatusAction;
use App\Controller\SupplierPaymentCreateAction;
use App\Controller\SupplierPaymentDeleteAction;
use App\Repository\SupplierPaymentRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Money paid to a supplier, mirroring the client-side Payment. Instead of a payment
 * method it names the account it comes out of: that is strictly more informative, and it
 * turns "you cannot pay a dollar invoice out of a sum account" into a currency comparison.
 */
#[ORM\Entity(repositoryClass: SupplierPaymentRepository::class)]
#[ORM\Table(name: 'supplier_payments')]
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
            controller: SupplierPaymentCreateAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/supplier_payments/{id}/change_status',
            controller: SupplierPaymentChangeStatusAction::class,
            denormalizationContext: ['groups' => ['supplier-payments-status:write']],
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/supplier_payments/{id}/auto_allocate',
            controller: SupplierPaymentAutoAllocateAction::class,
            security: "is_granted('ROLE_ADMIN')",
            deserialize: false,
            validate: false,
        ),
        new Delete(
            controller: SupplierPaymentDeleteAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    normalizationContext: ['groups' => ['supplier-payments:read']],
    denormalizationContext: ['groups' => ['supplier-payments:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['supplier' => 'exact', 'account' => 'exact', 'status' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['docDate', 'id'])]
class SupplierPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['supplier-payments:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['supplier-payments:read'])]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['supplier-payments:read', 'supplier-payments:write'])]
    private ?DateTimeInterface $docDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['supplier-payments:read'])]
    private ?DateTimeInterface $postedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['supplier-payments:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['supplier-payments:read', 'supplier-payments:write'])]
    private ?Supplier $supplier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['supplier-payments:read', 'supplier-payments:write'])]
    private ?CashAccount $account = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['supplier-payments:read', 'supplier-payments:write'])]
    #[Assert\Positive]
    private ?string $amount = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['supplier-payments:read', 'supplier-payments:write'])]
    private ?Currency $currency = null;

    #[ORM\Column(enumType: DocStatus::class)]
    #[Groups(['supplier-payments:read', 'supplier-payments-status:write'])]
    private ?DocStatus $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['supplier-payments:read', 'supplier-payments:write'])]
    private ?string $note = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['supplier-payments:read'])]
    private ?User $paidBy = null;

    /**
     * @var Collection<int, SupplierPaymentAllocation>
     */
    #[ORM\OneToMany(targetEntity: SupplierPaymentAllocation::class, mappedBy: 'supplierPayment', orphanRemoval: true)]
    #[Groups(['supplier-payments:read'])]
    private Collection $allocations;

    public function __construct()
    {
        $this->allocations = new ArrayCollection();
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

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

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

    public function getPaidBy(): ?User
    {
        return $this->paidBy;
    }

    public function setPaidBy(User $paidBy): static
    {
        $this->paidBy = $paidBy;

        return $this;
    }

    /**
     * @return Collection<int, SupplierPaymentAllocation>
     */
    public function getAllocations(): Collection
    {
        return $this->allocations;
    }

    public function addAllocation(SupplierPaymentAllocation $allocation): static
    {
        if (!$this->allocations->contains($allocation)) {
            $this->allocations->add($allocation);
            $allocation->setSupplierPayment($this);
        }

        return $this;
    }

    public function removeAllocation(SupplierPaymentAllocation $allocation): static
    {
        $this->allocations->removeElement($allocation);

        return $this;
    }
}
