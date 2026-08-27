<?php

declare(strict_types=1);

namespace App\Component\Core\Enums;

enum CashSessionStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
