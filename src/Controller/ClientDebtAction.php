<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Client\Dtos\ClientDebtDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ClientRepository;
use App\Repository\DebtRepository;
use Symfony\Component\Serializer\SerializerInterface;

class ClientDebtAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ClientRepository $clientRepository,
        private DebtRepository $debtRepository,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): array
    {
        $balanceByClient = $this->debtRepository->getBalanceByClient();

        $result = [];
        foreach ($this->clientRepository->findAll() as $client) {
            $balance = $balanceByClient[$client->getId()] ?? ['usd' => '0', 'uzs' => '0'];

            $result[] = new ClientDebtDto(
                $client->getId(),
                $client->getName(),
                $balance['usd'],
                $balance['uzs']
            );
        }

        return $result;
    }
}
