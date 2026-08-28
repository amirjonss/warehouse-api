<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\Cash\Dtos\CashHandoverRequestDto;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\CashEntry;
use App\Entity\CashSession;
use App\Service\CashHandoverService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @method CashHandoverRequestDto getDtoFromRequest(Request $request, string $dtoClass)
 */
class CashHandoverDeclareAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private CashHandoverService $cashHandoverService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(CashSession $data, Request $request): CashEntry
    {
        $me = $this->getUser();

        if ($data->getUser()->getId() !== $me->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Сдавать деньги можно только из своей смены.');
        }

        $dto = $this->getDtoFromRequest($request, CashHandoverRequestDto::class);
        $this->validate($dto);

        return $this->cashHandoverService->declareHandover(
            $data,
            $dto->getAmount(),
            $dto->getCurrency(),
            $dto->getNote(),
            $me
        );
    }
}
