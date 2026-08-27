<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Component\Product\Enums\Currency;
use App\Component\Product\Enums\UnitCode;
use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Reference => [name, currency, unit, minStock, priceUsd, priceUzs, category reference].
     *
     * "Test Product No Stock" is deliberately never received, so tests can rely on it to
     * trigger InsufficientBatchQuantityException.
     */
    private const DEFS = [
        'product-usd-1' => ['Test Product USD 1', Currency::USD, UnitCode::KG, '10.000', '5.00', null, 'category-1'],
        'product-usd-2' => ['Test Product USD 2', Currency::USD, UnitCode::KG, '10.000', '8.00', null, 'category-1'],
        'product-uzs-1' => ['Test Product UZS 1', Currency::UZS, UnitCode::L, '20.000', null, '60000.00', 'category-2'],
        'product-no-stock' => ['Test Product No Stock', Currency::USD, UnitCode::PCS, '5.000', '3.00', null, 'category-2'],
    ];

    public function getDependencies(): array
    {
        return [CategoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::DEFS as $reference => [$name, $currency, $unit, $minStock, $priceUsd, $priceUzs, $categoryRef]) {
            $product = new Product();
            $product
                ->setName($name)
                ->setCategory($this->getReference($categoryRef, Category::class))
                ->setCurrency($currency)
                ->setUnit($unit)
                ->setMinStock($minStock)
                ->setPriceUsd($priceUsd)
                ->setPriceUzs($priceUzs)
                ->setIsActive(true)
                ->setRemainingQty('0.000');

            $manager->persist($product);
            $this->addReference($reference, $product);
        }

        $manager->flush();
    }
}
