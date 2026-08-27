<?php

declare(strict_types=1);

namespace App\Component\Core\Enums;

/**
 * Подтверждена ли строка журнала администратором. Значение DECLARED осмысленно
 * только для HANDOVER: продавец отдал деньги, но владелец ещё не подтвердил приём.
 */
enum CashEntryStatus: string
{
    case CONFIRMED = 'confirmed';
    case DECLARED = 'declared';
}
