<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Component\Core\Enums\DocStatus;
use App\Component\Core\Enums\PaymentMethod;
use App\Component\Core\Enums\RateKind;
use App\Component\Product\Enums\Currency;
use App\Controller\PaymentAutoAllocateAction;
use App\Controller\PaymentChangeStatusAction;
use App\Controller\PaymentCreateAction;
use App\Controller\PaymentDeleteAction;
use App\Repository\PaymentRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_SALES')"),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(
            controller: PaymentCreateAction::class,
            security: "is_granted('ROLE_SALES')",
        ),
        new Post(
            uriTemplate: '/payments/{id}/change_status',
            controller: PaymentChangeStatusAction::class,
            denormalizationContext: ['groups' => ['payments-status:write']],
            security: "is_granted('ROLE_SALES')",
        ),
        new Post(
            uriTemplate: '/payments/{id}/auto_allocate',
            controller: PaymentAutoAllocateAction::class,
            security: "is_granted('ROLE_SALES')",
            deserialize: false,
            validate: false,
        ),
        new Delete(
            controller: PaymentDeleteAction::class,
            security: "is_granted('ROLE_SALES')",
        )
    ],
    normalizationContext: ['groups' => ['payments:read']],
    denormalizationContext: ['groups' => ['payments:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['client' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['docDate', 'id'])]
#[Assert\Expression(
    '(this.getRate() === null) === (this.getRateKind() === null)',
    message: 'rate and rateKind must be filled together',
)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payments:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['payments:read'])]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['payments:write', 'payments:read'])]
    private ?DateTimeInterface $docDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['payments:read'])]
    private ?DateTimeInterface $postedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payments:write', 'payments:read'])]
    private ?Client $client = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[Groups(['payments:write', 'payments:read'])]
    private ?string $amount = null;

    #[ORM\Column(enumType: Currency::class)]
    #[Groups(['payments:write', 'payments:read'])]
    private ?Currency $currency = null;

    #[ORM\Column(nullable: true, enumType: RateKind::class)]
    #[Groups(['payments:write', 'payments:read'])]
    private ?RateKind $rateKind = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    #[Groups(['payments:write', 'payments:read'])]
    private ?string $rate = null;

    #[ORM\Column(enumType: PaymentMethod::class)]
    #[Groups(['payments:write', 'payments:read'])]
    private ?PaymentMethod $method = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payments:read'])]
    private ?User $acceptedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['payments:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(enumType: DocStatus::class)]
    #[Groups(['payments-status:write', 'payments:read'])]
    private ?DocStatus $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['payments:write', 'payments:read'])]
    private ?string $note = null;

    /**
     * @var Collection<int, PaymentAllocation>
     */
    #[ORM\OneToMany(targetEntity: PaymentAllocation::class, mappedBy: 'payment', orphanRemoval: true)]
    #[Groups(['payments:read'])]
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

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

    public function getRateKind(): ?RateKind
    {
        return $this->rateKind;
    }

    public function setRateKind(?RateKind $rateKind): static
    {
        $this->rateKind = $rateKind;

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

    public function getMethod(): ?PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getAcceptedBy(): ?User
    {
        return $this->acceptedBy;
    }

    public function setAcceptedBy(?User $acceptedBy): static
    {
        $this->acceptedBy = $acceptedBy;

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
     * @return Collection<int, PaymentAllocation>
     */
    public function getAllocations(): Collection
    {
        return $this->allocations;
    }

    public function addAllocation(PaymentAllocation $allocation): static
    {
        if (!$this->allocations->contains($allocation)) {
            $this->allocations->add($allocation);
            $allocation->setPayment($this);
        }

        return $this;
    }

    public function removeAllocation(PaymentAllocation $allocation): static
    {
        if ($this->allocations->removeElement($allocation)) {
            if ($allocation->getPayment() === $this) {
                $allocation->setPayment(null);
            }
        }

        return $this;
    }
}
