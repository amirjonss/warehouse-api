<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Expense;
use App\Service\CashExpenseService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class ExpenseDeleteAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private CashExpenseService $cashExpenseService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Expense $data): Response
    {
        $this->cashExpenseService->delete($data);

        return $this->responseEmpty();
    }
}
