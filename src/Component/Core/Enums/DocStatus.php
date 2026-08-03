<?php

namespace App\Component\Core\Enums;

enum DocStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case CANCELLED = 'cancelled';
}
