<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Cash\Dtos\CashSessionSummaryDto;
use App\Component\Cash\Dtos\CashTurnoverItemDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\CashSession;
use App\Repository\CashSessionRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\SerializerInterface;

class CashSessionSummaryAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private CashSessionRepository $cashSessionRepository,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(CashSession $data): CashSessionSummaryDto
    {
        $me = $this->getUser();

        if ($data->getUser()->getId() !== $me->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Чужая смена доступна только администратору.');
        }

        $turnover = array_map(
            static fn (array $row) => new CashTurnoverItemDto(
                $row['method'],
                $row['currency'],
                $row['total'],
                $row['count'],
            ),
            $this->cashSessionRepository->getTurnover($data)
        );

        return new CashSessionSummaryDto(
            $data->getBalanceUsd(),
            $data->getBalanceUzs(),
            $data->getUnconfirmedUsd(),
            $data->getUnconfirmedUzs(),
            $turnover
        );
    }
}
