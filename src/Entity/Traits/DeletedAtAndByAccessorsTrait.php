<?php

declare(strict_types=1);

namespace App\Entity\Traits;

trait DeletedAtAndByAccessorsTrait
{
    use DeletedAtAccessorsTrait;
    use DeletedByAccessorsTrait;
}
