<?php

namespace App\Repository;

use App\Entity\Expense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Expense|null find($id, $lockMode = null, $lockVersion = null)
 * @method Expense|null findOneBy(array $criteria, array $orderBy = null)
 * @method Expense[]    findAll()
 * @method Expense[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * @param string|null $from включительная нижняя граница doc_date (YYYY-MM-DD)
     * @param string|null $to   исключающая верхняя граница doc_date (YYYY-MM-DD)
     */
    public function getSummary(?string $from, ?string $to): string
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->select('COALESCE(SUM(amount), 0) AS total_amount')->from('expenses');

        if ($from !== null) {
            $qb->andWhere('doc_date >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('doc_date < :to')->setParameter('to', $to);
        }

        $row = $qb->executeQuery()->fetchAssociative();

        return (string) $row['total_amount'];
    }
}
