<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ([1, 2] as $i) {
            $category = new Category();
            $category->setName('Test Category ' . $i);
            $manager->persist($category);
            $this->addReference('category-' . $i, $category);
        }

        $manager->flush();
    }
}
