<?php

declare(strict_types=1);

namespace App\Component\Cash\Dtos;

use App\Component\Product\Enums\Currency;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Заявка продавца о сдаче денег владельцу. Сумма всегда в одной валюте: отдал
 * и доллары, и сумы — значит два документа, а не один с пересчётом.
 */
class CashHandoverRequestDto
{
    public function __construct(
        #[Groups(['cash-handover:write'])]
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
