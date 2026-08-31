<?php

declare(strict_types=1);

namespace App\Component\Account\Enums;

/**
 * Where the company's money physically sits. Together with the currency this is the
 * identity of an account: there is exactly one account per (kind, currency) pair.
 */
enum CashAccountKind: string
{
    /** Notes in the safe. */
    case CASH = 'cash';
    /** Money that arrived on the company card. */
    case CARD = 'card';
    /** The bank account. */
    case BANK = 'bank';
}
