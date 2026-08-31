<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Receipt;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<Receipt>
 */
class ReceiptItemOutstandingProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $inner,
        private readonly ReceiptOutstandingEnricher $enricher,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $receipt = $this->inner->provide($operation, $uriVariables, $context);

        if ($receipt instanceof Receipt) {
            $this->enricher->enrich([$receipt]);
        }

        return $receipt;
    }
}
