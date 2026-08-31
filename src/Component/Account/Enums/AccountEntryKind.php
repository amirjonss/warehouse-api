<?php

declare(strict_types=1);

namespace App\Component\Account\Enums;

/**
 * The kind of a treasury journal row. As with CashEntry, the sign of the amount comes
 * from the row itself rather than from the kind.
 */
enum AccountEntryKind: string
{
    /** The balance the account started life with, entered once. */
    case OPENING = 'opening';
    /** Cash that arrived from a seller's shift. */
    case HANDOVER = 'handover';
    /** A card or transfer payment from a client, which never passes through a shift. */
    case COLLECT = 'collect';
    /** Money leaving one account for another: cash deposited at the bank, or an exchange. */
    case TRANSFER_OUT = 'transfer_out';
    /** The receiving side of the same move. */
    case TRANSFER_IN = 'transfer_in';
    /** Paid out to a supplier. */
    case SUPPLIER_PAYMENT = 'supplier_payment';
    /** Spent on business needs straight out of a company account. */
    case EXPENSE = 'expense';
}
