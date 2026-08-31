<?php

declare(strict_types=1);

namespace App\Component\Account\Dtos;

use App\Component\Cash\Dtos\CashHandoverRequestDto;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class OpeningBalanceRequestDto
{
    public function __construct(
        #[Groups(['opening-balance:write'])]
        #[Assert\NotBlank(message: 'Укажите начальный остаток.')]
        #[Assert\Regex(
            pattern: CashHandoverRequestDto::DECIMAL_PATTERN,
            message: 'Остаток должен быть числом, например 150000.00.'
        )]
        private string $amount = '0',
        #[Groups(['opening-balance:write'])]
        private ?string $note = null,
    ) {
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}
