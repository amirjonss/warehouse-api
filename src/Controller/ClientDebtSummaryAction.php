<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Client\Dtos\ClientDebtSummaryDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ClientRepository;
use Symfony\Component\Serializer\SerializerInterface;

class ClientDebtSummaryAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ClientRepository $clientRepository,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): ClientDebtSummaryDto
    {
        $summary = $this->clientRepository->getDebtSummary();

        return new ClientDebtSummaryDto($summary['count'], $summary['totalDebtUsd'], $summary['totalDebtUzs']);
    }
}
