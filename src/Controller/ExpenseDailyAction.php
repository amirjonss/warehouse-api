<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Expense\Dtos\ExpenseDailyDto;
use App\Component\Expense\Dtos\ExpenseDailyItemDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\ExpenseRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;

class ExpenseDailyAction extends AbstractController
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

    public function __invoke(): ExpenseDailyDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $from = $request?->query->get('from');
        $to = $request?->query->get('to');

        $rows = $this->expenseRepository->getDailyTotals($from, $to);

        $items = array_map(
            fn (array $row) => new ExpenseDailyItemDto(
                (string) $row['doc_date'],
                (string) $row['total'],
                (int) $row['count'],
            ),
            $rows,
        );

        return new ExpenseDailyDto($items);
    }
}
