<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Interfaces\CreatedAtSettableInterface;
use App\Entity\Traits\CreatedAtAccessorsTrait;
use App\Repository\ExchangeRateRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\Table(name: 'exchange_rates')]
#[ApiResource]
#[Assert\Expression(
    'this.getRateBuy() === null || this.getRateSell() === null || this.getRateSell() >= this.getRateBuy()',
    message: 'rate_sell must not be lower than rate_buy',
)]
class ExchangeRate implements CreatedAtSettableInterface
{
    use CreatedAtAccessorsTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?DateTimeInterface $rateDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private ?string $rateBuy = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    private ?string $rateSell = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt = null;

    public function getRateDate(): ?DateTimeInterface
    {
        return $this->rateDate;
    }

    public function setRateDate(DateTimeInterface $rateDate): self
    {
        $this->rateDate = $rateDate;

        return $this;
    }

    public function getRateBuy(): ?string
    {
        return $this->rateBuy;
    }

    public function setRateBuy(string $rateBuy): self
    {
        $this->rateBuy = $rateBuy;

        return $this;
    }

    public function getRateSell(): ?string
    {
        return $this->rateSell;
    }

    public function setRateSell(string $rateSell): self
    {
        $this->rateSell = $rateSell;

        return $this;
    }
}
