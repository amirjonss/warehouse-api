<?php

declare(strict_types=1);

namespace App\Component\Core\Enums;

enum DocumentType: string
{
    case RECEIPT = 'receipt';
    case SALE = 'sale';
    case WRITEOFF = 'writeoff';
    case PAYMENT = 'payment';
}
