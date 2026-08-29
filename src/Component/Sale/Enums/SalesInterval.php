<?php

declare(strict_types=1);

namespace App\Component\Sale\Enums;

/**
 * How finely the sales analysis slices the period. The value doubles as the argument for
 * date_trunc, which is why nothing else may ever be interpolated into that call.
 */
enum SalesInterval: string
{
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
}
