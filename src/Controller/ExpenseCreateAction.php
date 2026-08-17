<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Expense\ExpenseFactory;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\Expense;
use Symfony\Component\Serializer\SerializerInterface;

class ExpenseCreateAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private ExpenseFactory $expenseFactory,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(Expense $data): Expense
    {
        return $this->expenseFactory->create(
            $this->getUser(),
            $data->getDescription(),
            $data->getAmount(),
            $data->getDocDate()
        );
    }
}
