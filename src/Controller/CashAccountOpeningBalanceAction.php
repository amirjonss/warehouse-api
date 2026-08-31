<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Account\Dtos\OpeningBalanceRequestDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\AccountEntry;
use App\Entity\CashAccount;
use App\Service\CashAccountOpeningBalanceService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @method OpeningBalanceRequestDto getDtoFromRequest(Request $request, string $dtoClass)
 */
class CashAccountOpeningBalanceAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private CashAccountOpeningBalanceService $cashAccountOpeningBalanceService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(CashAccount $data, Request $request): AccountEntry
    {
        $dto = $this->getDtoFromRequest($request, OpeningBalanceRequestDto::class);
        // Validate before the service runs, or a non-numeric amount blows up inside
        // bcmath as a 500.
        $this->validate($dto);

        return $this->cashAccountOpeningBalanceService->set(
            $data,
            $dto->getAmount(),
            $dto->getNote(),
            $this->getUser()
        );
    }
}
