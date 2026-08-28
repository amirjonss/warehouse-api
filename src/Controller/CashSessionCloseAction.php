<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Cash\Dtos\CashCloseRequestDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\CashSession;
use App\Service\CashSessionCloseService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @method CashCloseRequestDto getDtoFromRequest(Request $request, string $dtoClass)
 */
class CashSessionCloseAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private CashSessionCloseService $cashSessionCloseService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(CashSession $data, Request $request): CashSession
    {
        $dto = $this->getDtoFromRequest($request, CashCloseRequestDto::class);
        // See CashHandoverDeclareAction: validate before the service runs, or a
        // non-numeric amount blows up inside bcmath as a 500.
        $this->validate($dto);

        return $this->cashSessionCloseService->close(
            $data,
            $dto->getAcceptedUsd(),
            $dto->getAcceptedUzs(),
            $dto->getNote(),
            $this->getUser()
        );
    }
}
