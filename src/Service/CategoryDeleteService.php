<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Category\Exceptions\CategoryInUseException;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;

class CategoryDeleteService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function delete(Category $category): void
    {
        // products.category_id is NOT NULL, so removing a category that still has products
        // would surface as a raw foreign-key violation from the database.
        if (!$category->getProducts()->isEmpty()) {
            throw new CategoryInUseException(sprintf(
                'Category "%s" still has %d product(s) and cannot be deleted.',
                $category->getName(),
                $category->getProducts()->count()
            ));
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
