<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

class LowStockFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $value = $context['filters']['lowStock'] ?? null;
        if ($value === null || !filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere("{$alias}.remainingQty <= {$alias}.minStock");
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'lowStock' => [
                'property' => null,
                'type' => 'bool',
                'required' => false,
                'description' => 'Filter to products where remainingQty <= minStock (includes out-of-stock).',
            ],
        ];
    }
}
