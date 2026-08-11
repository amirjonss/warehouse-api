<?php

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Expense\Dtos\ExpenseSummaryDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ExpenseRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

class ExpenseSummaryAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ExpenseRepository $expenseRepository,
        private RequestStack $requestStack,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): ExpenseSummaryDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $from = $request?->query->get('from');
        $to = $request?->query->get('to');

        return new ExpenseSummaryDto($this->expenseRepository->getSummary($from, $to));
    }
}
