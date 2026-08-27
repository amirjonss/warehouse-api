<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Supplier;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SupplierFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ([1, 2] as $i) {
            $supplier = new Supplier();
            $supplier
                ->setName('Test Supplier ' . $i)
                ->setContact('Contact ' . $i)
                ->setPhone('+99890000000' . $i)
                ->setAddress('Tashkent, street ' . $i)
                ->setIsActive(true);
            $manager->persist($supplier);
            $this->addReference('supplier-' . $i, $supplier);
        }

        $manager->flush();
    }
}
