<?php

namespace App\Component\Core\Enums;

enum MovementType: string
{
    case IN = 'in';
    case OUT = 'out';
    case WRITEOFF = 'writeoff';
    case ADJUST = 'adjust';
}
