<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Account\Dtos\WalletAccountDto;
use App\Component\Account\Dtos\WalletSummaryDto;
use App\Component\Product\Enums\Currency;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Repository\CashAccountRepository;
use Symfony\Component\Serializer\SerializerInterface;

class WalletSummaryAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private CashAccountRepository $cashAccountRepository,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(): WalletSummaryDto
    {
        $totals = $this->cashAccountRepository->getWalletTotals();

        $accounts = [];
        foreach ($this->cashAccountRepository->findBy([], ['id' => 'ASC']) as $account) {
            $accounts[] = new WalletAccountDto(
                $account->getKind()->value,
                $account->getCurrency()->value,
                $account->getName(),
                $account->getBalance()
            );
        }

        return new WalletSummaryDto(
            $totals[Currency::USD->value] ?? '0.00',
            $totals[Currency::UZS->value] ?? '0.00',
            $accounts
        );
    }
}
