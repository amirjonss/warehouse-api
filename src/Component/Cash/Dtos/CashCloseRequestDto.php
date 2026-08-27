<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Закрытие смены: владелец вводит, сколько денег принял фактически по каждой
 * валюте. Разница с остатком станет недостачей — отдельной строкой, а не тихим
 * обнулением.
 */
class CashCloseRequestDto
{
    public function __construct(
        #[Groups(['cash-close:write'])]
        private string $acceptedUsd = '0',
        #[Groups(['cash-close:write'])]
        private string $acceptedUzs = '0',
        #[Groups(['cash-close:write'])]
        private ?string $note = null,
    ) {
    }

    public function getAcceptedUsd(): string
    {
        return $this->acceptedUsd;
    }

    public function getAcceptedUzs(): string
    {
        return $this->acceptedUzs;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}
