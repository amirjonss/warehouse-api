<?php

declare(strict_types=1);

namespace App\Component\Core\Enums;

/**
 * Whether an administrator has confirmed the journal row. DECLARED only makes sense for
 * HANDOVER: the seller handed the money over, but the owner has not acknowledged it yet.
 */
enum CashEntryStatus: string
{
    case CONFIRMED = 'confirmed';
    case DECLARED = 'declared';
}
