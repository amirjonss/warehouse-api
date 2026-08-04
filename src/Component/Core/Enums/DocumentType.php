<?php

namespace App\Component\Core\Enums;

enum DocumentType: string
{
    case RECEIPT = 'receipt';
    case SALE = 'sale';
    case WRITEOFF = 'writeoff';
}
