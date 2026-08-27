<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Expense\ExpenseFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Expense;
use App\Service\CashExpenseService;
use Symfony\Component\Serializer\SerializerInterface;

class ExpenseCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ExpenseFactory $expenseFactory,
        private CashExpenseService $cashExpenseService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Expense $data): Expense
    {
        // Фабрика требует непустые значения, поэтому валидируем до неё — иначе
        // отсутствующая валюта падает TypeError'ом в 500 вместо внятного 422.
        $this->validate($data);

        $expense = $this->expenseFactory->create(
            $this->getUser(),
            $data->getDescription(),
            $data->getAmount(),
            $data->getCurrency(),
            $data->getDocDate()
        );

        // Если у сотрудника открыта смена, деньги уходят из его наличности —
        // сервис проверит остаток и запишет строку в журнал кассы.
        return $this->cashExpenseService->create($expense);
    }
}
