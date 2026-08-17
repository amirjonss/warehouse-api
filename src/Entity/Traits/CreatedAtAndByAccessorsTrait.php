<?php

declare(strict_types=1);

namespace App\Entity\Traits;

trait CreatedAtAndByAccessorsTrait
{
    use CreatedAtAccessorsTrait;
    use CreatedByAccessorsTrait;
}
