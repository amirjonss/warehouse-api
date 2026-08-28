<?php

declare(strict_types=1);

namespace App\Component\Core\Enums;

/**
 * The kind of a cash journal row. The sign of the amount comes from the row itself
 * rather than from the kind: COLLECT arrives positive, the rest negative, the same way
 * StockMovement handles quantity.
 */
enum CashEntryKind: string
{
    /** Cash taken from a client: grows what the seller is holding. */
    case COLLECT = 'collect';
    /** Money spent on business needs out of the cash collected. */
    case EXPENSE = 'expense';
    /** Cash handed over to the owner, both partially and when the session is closed. */
    case HANDOVER = 'handover';
    /** The gap found at closing: the system expected more than was brought in. */
    case SHORTAGE = 'shortage';
}
