<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Client\Dtos\ClientDebtAgingDto;
use App\Component\Client\Dtos\ClientDebtAgingItemDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ClientRepository;
use Symfony\Component\Serializer\SerializerInterface;

class ClientDebtAgingAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ClientRepository $clientRepository,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): ClientDebtAgingDto
    {
        $aging = $this->clientRepository->getDebtAging();

        $items = array_map(
            fn (int $clientId, string $oldestDebtDate) => new ClientDebtAgingItemDto($clientId, $oldestDebtDate),
            array_keys($aging),
            array_values($aging),
        );

        return new ClientDebtAgingDto($items);
    }
}
