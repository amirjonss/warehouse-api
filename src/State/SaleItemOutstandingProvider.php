<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Sale;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Декорирует стандартный Doctrine-провайдер одной продажи и дозаполняет
 * остаток долга (USD/UZS).
 *
 * @implements ProviderInterface<Sale>
 */
class SaleItemOutstandingProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $inner,
        private readonly SaleOutstandingEnricher $enricher,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $sale = $this->inner->provide($operation, $uriVariables, $context);

        if ($sale instanceof Sale) {
            $this->enricher->enrich([$sale]);
        }

        return $sale;
    }
}
