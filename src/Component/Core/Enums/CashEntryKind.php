<?php

declare(strict_types=1);

namespace App\Component\Core\Enums;

/**
 * Тип строки журнала наличных. Знак суммы задаётся не типом, а самой строкой:
 * COLLECT приходит с плюсом, остальные — с минусом (как quantity у StockMovement).
 */
enum CashEntryKind: string
{
    /** Приём наличных от клиента — растит остаток на руках у продавца. */
    case COLLECT = 'collect';
    /** Трата на нужды из собранной наличности. */
    case EXPENSE = 'expense';
    /** Сдача денег владельцу — как частичная, так и при закрытии смены. */
    case HANDOVER = 'handover';
    /** Расхождение при закрытии: система показывала больше, чем принесли. */
    case SHORTAGE = 'shortage';
}
