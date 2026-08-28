<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class CashCloseRequestDto
{
    public function __construct(
        #[Groups(['cash-close:write'])]
        #[Assert\NotBlank(message: 'Укажите принятую сумму в USD (0, если долларов не было).')]
        #[Assert\Regex(
            pattern: CashHandoverRequestDto::DECIMAL_PATTERN,
            message: 'Принятая сумма в USD должна быть числом, например 450.00.'
        )]
        private string $acceptedUsd = '0',
        #[Groups(['cash-close:write'])]
        #[Assert\NotBlank(message: 'Укажите принятую сумму в UZS (0, если сумов не было).')]
        #[Assert\Regex(
            pattern: CashHandoverRequestDto::DECIMAL_PATTERN,
            message: 'Принятая сумма в UZS должна быть числом, например 900000.00.'
        )]
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
