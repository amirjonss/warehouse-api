<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Sale;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Декорирует стандартный Doctrine-провайдер коллекции продаж и дозаполняет
 * остаток долга (USD/UZS) одним запросом на всю страницу.
 *
 * @implements ProviderInterface<Sale>
 */
class SaleCollectionOutstandingProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $inner,
        private readonly SaleOutstandingEnricher $enricher,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $result = $this->inner->provide($operation, $uriVariables, $context);

        $sales = [];
        foreach ($result as $item) {
            if ($item instanceof Sale) {
                $sales[] = $item;
            }
        }

        $this->enricher->enrich($sales);

        return $result;
    }
}
