<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use App\Component\Product\Enums\Currency;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class CashHandoverRequestDto
{
    public const DECIMAL_PATTERN = '/^-?\d+(\.\d+)?$/';

    public function __construct(
        #[Groups(['cash-handover:write'])]
        #[Assert\NotBlank(message: 'Укажите сумму сдачи.')]
        #[Assert\Regex(pattern: self::DECIMAL_PATTERN, message: 'Сумма должна быть числом, например 150000.00.')]
        private string $amount = '0',
        #[Groups(['cash-handover:write'])]
        private Currency $currency = Currency::UZS,
        #[Groups(['cash-handover:write'])]
        private ?string $note = null,
    ) {
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}
