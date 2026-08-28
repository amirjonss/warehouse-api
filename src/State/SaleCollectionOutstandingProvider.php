<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Sale;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decorates the stock Doctrine collection provider for sales and fills in the
 * outstanding debt (USD/UZS) with one query for the whole page.
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
