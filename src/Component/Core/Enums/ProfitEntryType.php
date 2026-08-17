<?php

declare(strict_types=1);

namespace App\Component\Core\Enums;

enum ProfitEntryType: string
{
    case REALIZED = 'realized';
    case REVERSED = 'reversed';
}
