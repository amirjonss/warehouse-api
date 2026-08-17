<?php

declare(strict_types=1);

namespace App\Component\Core\Enums;

enum RateKind: string
{
    case BUY = 'buy';
    case SELL = 'sell';
}
