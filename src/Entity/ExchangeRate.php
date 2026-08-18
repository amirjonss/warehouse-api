<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Interfaces\CreatedAtSettableInterface;
use App\Entity\Interfaces\CreatedBySettableInterface;
use App\Entity\Traits\CreatedAtAccessorsTrait;
use App\Entity\Traits\CreatedByAccessorsTrait;
use App\Repository\ExchangeRateRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\Table(name: 'exchange_rates')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_SALES')"),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(security: "is_granted('ROLE_SALES')"),
        new Patch(security: "is_granted('ROLE_SALES')"),
        new Delete(security: "is_granted('ROLE_SALES')"),
    ],
    normalizationContext: ['groups' => ['exchange-rates:read']],
    denormalizationContext: ['groups' => ['exchange-rates:write']],
    paginationItemsPerPage: 20
)]
#[Assert\Expression(
    'this.getRateBuy() === null || this.getRateSell() === null || this.getRateSell() >= this.getRateBuy()',
    message: 'rate_sell must not be lower than rate_buy',
)]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class ExchangeRate implements CreatedAtSettableInterface, CreatedBySettableInterface
{
    use CreatedAtAccessorsTrait;
    use CreatedByAccessorsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['exchange-rates:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(["exchange-rates:write", 'exchange-rates:read'])]
    private ?int $rateBuy = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(["exchange-rates:write", 'exchange-rates:read'])]
    private ?int $rateSell = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['exchange-rates:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['exchange-rates:read'])]
    private ?User $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRateBuy(): ?int
    {
        return $this->rateBuy;
    }

    public function setRateBuy(?int $rateBuy): void
    {
        $this->rateBuy = $rateBuy;
    }

    public function getRateSell(): ?int
    {
        return $this->rateSell;
    }

    public function setRateSell(?int $rateSell): void
    {
        $this->rateSell = $rateSell;
    }
}
