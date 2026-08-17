<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StockMovement|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockMovement|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockMovement[]    findAll()
 * @method StockMovement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovement::class);
    }

    /**
     * @return array<int, string> остаток, индексированный по product id
     */
    public function getRemainingQtyByProduct(): array
    {
        $rows = $this->createQueryBuilder('sm')
            ->select('IDENTITY(sm.product) AS productId, SUM(sm.quantity) AS remainingQty')
            ->groupBy('sm.product')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['productId']] = (string) $row['remainingQty'];
        }

        return $result;
    }
}
