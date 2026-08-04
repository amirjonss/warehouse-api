<?php

namespace App\Component\SaleItem;

use App\Entity\SaleItem;

class SaleItemFactory
{
    public function create(SaleItem $data): SaleItem
    {
        $saleItem = new SaleItem();
        $saleItem
            ->setSale($data->getSale())
            ->setProduct($data->getProduct())
            ->setQuantity($data->getQuantity())
            ->setPrice($data->getPrice())
            ->setCurrency($data->getCurrency())
            ->setTotal(bcmul($data->getPrice(), $data->getQuantity(), 2));

        return $saleItem;
    }
}
