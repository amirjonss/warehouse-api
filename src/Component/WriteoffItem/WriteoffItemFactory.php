<?php

declare(strict_types=1);

namespace App\Component\WriteoffItem;

use App\Entity\WriteoffItem;

class WriteoffItemFactory
{
    public function create(WriteoffItem $data): WriteoffItem
    {
        $writeoffItem = new WriteoffItem();
        $writeoffItem
            ->setWriteoff($data->getWriteoff())
            ->setProduct($data->getProduct())
            ->setBatch($data->getBatch())
            ->setQuantity($data->getQuantity());

        $data->getWriteoff()->addItem($writeoffItem);

        return $writeoffItem;
    }
}
