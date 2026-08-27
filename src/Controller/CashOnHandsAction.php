<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Cash\Dtos\CashOnHandsDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\CashSessionRepository;
use Symfony\Component\Serializer\SerializerInterface;

class CashOnHandsAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private CashSessionRepository $cashSessionRepository,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): CashOnHandsDto
    {
        $totals = $this->cashSessionRepository->getTotalOnHands();

        return new CashOnHandsDto(
            $totals['balanceUsd'],
            $totals['balanceUzs'],
            $totals['unconfirmedUsd'],
            $totals['unconfirmedUzs'],
            $totals['openSessions'],
        );
    }
}
