<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Profit\Dtos\ProfitSummaryDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ProfitRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

class ProfitSummaryAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ProfitRepository $profitRepository,
        private RequestStack $requestStack,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): ProfitSummaryDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $from = $request?->query->get('from');
        $to = $request?->query->get('to');

        $summary = $this->profitRepository->getSummary($from, $to);

        return new ProfitSummaryDto($summary['totalUsd'], $summary['totalUzs']);
    }
}
