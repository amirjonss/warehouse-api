<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class HasDebtFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $value = $context['filters']['hasDebt'] ?? null;
        if ($value === null || !filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere("{$alias}.debtUsd > 0 OR {$alias}.debtUzs > 0");
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'hasDebt' => [
                'property' => null,
                'type' => 'bool',
                'required' => false,
                'description' => 'Keeps only rows where debtUsd > 0 or debtUzs > 0.',
            ],
        ];
    }
}
