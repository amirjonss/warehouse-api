<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Expense\ExpenseFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Expense;
use App\Service\CashExpenseService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        $this->validate($data);

        // Sellers spend what is in their own bag; the company's accounts are the owner's.
        if ($data->getAccount() !== null && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException(
                'Расход со счёта компании может провести только владелец — тратьте из своей смены.'
            );
        }

        $expense = $this->expenseFactory->create(
            $this->getUser(),
            $data->getDescription(),
            $data->getAmount(),
            $data->getCurrency(),
            $data->getDocDate(),
            $data->getAccount()
        );

        return $this->cashExpenseService->create($expense);
    }
}
