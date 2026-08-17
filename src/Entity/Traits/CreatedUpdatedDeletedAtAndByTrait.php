<?php

declare(strict_types=1);

namespace App\Entity\Traits;

trait CreatedUpdatedDeletedAtAndByTrait
{
    use CreatedAtAndByAccessorsTrait;
    use UpdatedAtAndByAccessorsTrait;
    use DeletedAtAndByAccessorsTrait;
}
