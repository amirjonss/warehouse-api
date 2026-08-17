<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use DateTimeInterface;

trait UpdatedAtAccessorsTrait
{
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
