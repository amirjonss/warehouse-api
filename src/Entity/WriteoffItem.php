<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\WriteoffItemCreateAction;
use App\Controller\WriteoffItemDeleteAction;
use App\Controller\WriteoffItemUpdateAction;
use App\Repository\WriteoffItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WriteoffItemRepository::class)]
#[ORM\Table(name: 'writeoff_items')]
#[ORM\UniqueConstraint(name: 'uniq_writeoff_items_writeoff_batch', columns: ['writeoff_id', 'batch_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            controller: WriteoffItemCreateAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            controller: WriteoffItemUpdateAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Delete(
            controller: WriteoffItemDeleteAction::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    denormalizationContext: ['groups' => ['writeoff:write']],
)]
class WriteoffItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['writeoff:write'])]
    private ?Writeoff $writeoff = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['writeoff:write'])]
    private ?Product $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['writeoff:write'])]
    private ?Batch $batch = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[Groups(['writeoff:write'])]
    #[Assert\Positive]
    private ?string $quantity = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWriteoff(): ?Writeoff
    {
        return $this->writeoff;
    }

    public function setWriteoff(?Writeoff $writeoff): static
    {
        $this->writeoff = $writeoff;

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

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }
}
