<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use DateTimeInterface;

trait DeletedAtAccessorsTrait
{
    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
}
