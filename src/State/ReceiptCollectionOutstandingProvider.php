<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Receipt;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decorates the stock Doctrine collection provider for receipts and fills in what is
 * still owed to the supplier (USD/UZS) with one query for the whole page.
 *
 * @implements ProviderInterface<Receipt>
 */
class ReceiptCollectionOutstandingProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $inner,
        private readonly ReceiptOutstandingEnricher $enricher,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $result = $this->inner->provide($operation, $uriVariables, $context);

        $receipts = [];
        foreach ($result as $item) {
            if ($item instanceof Receipt) {
                $receipts[] = $item;
            }
        }

        $this->enricher->enrich($receipts);

        return $result;
    }
}
