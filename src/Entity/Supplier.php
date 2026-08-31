<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\SupplierDeleteAction;
use App\Filter\HasDebtFilter;
use App\Repository\SupplierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table(name: 'suppliers')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(
            controller: SupplierDeleteAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],

)]
#[ApiFilter(HasDebtFilter::class)]
#[ApiFilter(OrderFilter::class, properties: ['debtUsd', 'debtUzs', 'name'])]
class Supplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['batch:read', 'receipts:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['batch:read', 'receipts:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contact = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?bool $isActive = null;

    /** What we owe this supplier. SupplierDebtFactory is the only writer. */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    private ?string $debtUsd = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    #[ApiProperty(writable: false)]
    private ?string $debtUzs = '0.00';

    public function getId(): ?int
    {
        return $this->id;
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

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

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

    public function getDebtUsd(): ?string
    {
        return $this->debtUsd;
    }

    public function setDebtUsd(string $debtUsd): static
    {
        $this->debtUsd = $debtUsd;

        return $this;
    }

    public function getDebtUzs(): ?string
    {
        return $this->debtUzs;
    }

    public function setDebtUzs(string $debtUzs): static
    {
        $this->debtUzs = $debtUzs;

        return $this;
    }
}
