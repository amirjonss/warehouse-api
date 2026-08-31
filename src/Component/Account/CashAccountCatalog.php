<?php

declare(strict_types=1);

namespace App\Component\Account;

use App\Component\Account\Enums\CashAccountKind;
use App\Component\Product\Enums\Currency;

/**
 * The closed set of company accounts. There is no dollar bank account and no dollar
 * card — dollars exist only as cash — so USD appears here exactly once.
 *
 * The migration that creates the table repeats this list literally: migrations are
 * frozen in time and must not reach into application code.
 */
class CashAccountCatalog
{
    /** @var list<array{kind: CashAccountKind, currency: Currency, name: string, reference: string}> */
    public const ACCOUNTS = [
        ['kind' => CashAccountKind::CASH, 'currency' => Currency::UZS, 'name' => 'Наличные UZS', 'reference' => 'account-cash-uzs'],
        ['kind' => CashAccountKind::CASH, 'currency' => Currency::USD, 'name' => 'Наличные USD', 'reference' => 'account-cash-usd'],
        ['kind' => CashAccountKind::CARD, 'currency' => Currency::UZS, 'name' => 'Карта', 'reference' => 'account-card-uzs'],
        ['kind' => CashAccountKind::BANK, 'currency' => Currency::UZS, 'name' => 'Счёт / банк', 'reference' => 'account-bank-uzs'],
    ];
}
