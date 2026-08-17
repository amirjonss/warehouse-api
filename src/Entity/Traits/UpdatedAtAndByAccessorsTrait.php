<?php

declare(strict_types=1);

namespace App\Entity\Traits;

trait UpdatedAtAndByAccessorsTrait
{
    use UpdatedAtAccessorsTrait;
    use UpdatedByAccessorsTrait;
}
