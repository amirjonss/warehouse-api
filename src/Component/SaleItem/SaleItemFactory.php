<?php

declare(strict_types=1);

namespace App\Component\SaleItem;

use App\Component\Product\Enums\Currency;
use App\Component\SaleItem\Exceptions\MissingDefaultPriceException;
use App\Entity\Product;
use App\Entity\SaleItem;

class SaleItemFactory
{
    public function create(SaleItem $data): SaleItem
    {
        [$price, $currency] = $this->resolvePriceAndCurrency($data);

        $saleItem = new SaleItem();
        $saleItem
            ->setSale($data->getSale())
            ->setProduct($data->getProduct())
            ->setQuantity($data->getQuantity())
            ->setPrice($price)
            ->setCurrency($currency)
            ->setRate($data->getRate())
            ->setTotal(bcmul($price, $data->getQuantity(), 2));

        return $saleItem;
    }

    /**
     * @return array{0: string, 1: Currency}
     */
    private function resolvePriceAndCurrency(SaleItem $data): array
    {
        $product = $data->getProduct();
        $currency = $data->getCurrency() ?? $product->getCurrency();
        $price = $data->getPrice();

        if ($price === null) {
            $price = $this->getDefaultPrice($product, $currency);
        }

        return [$price, $currency];
    }

    private function getDefaultPrice(Product $product, Currency $currency): string
    {
        $price = $currency === Currency::USD ? $product->getPriceUsd() : $product->getPriceUzs();

        if ($price === null) {
            throw new MissingDefaultPriceException(sprintf(
                'Product "%s" has no default price in %s and none was provided.',
                $product->getName(),
                $currency->value
            ));
        }

        return $price;
    }
}
