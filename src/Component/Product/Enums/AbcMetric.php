<?php

declare(strict_types=1);

namespace App\Component\Product\Enums;

/**
 * What the ABC analysis ranks products by. Revenue and profit are money and get converted
 * to a single currency; quantity is not, so it needs no rate at all.
 */
enum AbcMetric: string
{
    case REVENUE = 'revenue';
    case QUANTITY = 'quantity';
    case PROFIT = 'profit';

    public function isMoney(): bool
    {
        return $this !== self::QUANTITY;
    }
}
