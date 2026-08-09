<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Component\Client\Dtos\ClientDebtDto;
use App\Controller\ClientDebtAction;
use App\Repository\ClientRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_SALES')"),
        new GetCollection(
            uriTemplate: '/clients/debt',
            controller: ClientDebtAction::class,
            security: "is_granted('ROLE_SALES')",
            output: ClientDebtDto::class,
            read: false
        ),
        new Get(security: "is_granted('ROLE_SALES')"),
        new Post(security: "is_granted('ROLE_SALES')"),
        new Patch(security: "is_granted('ROLE_SALES')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['sales:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['sales:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contact = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column]
    private ?bool $isActive = null;

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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
