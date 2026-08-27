<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Validator\ValidatorInterface;
use App\Component\User\CurrentUser;
use App\Controller\Base\AbstractController;
use App\Entity\CashSession;
use App\Service\CashSessionOpenService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\SerializerInterface;

class CashSessionOpenAction extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CurrentUser $currentUser,
        private CashSessionOpenService $cashSessionOpenService,
    ) {
        parent::__construct($serializer, $validator, $currentUser);
    }

    public function __invoke(CashSession $data): CashSession
    {
        $me = $this->getUser();
        // Продавец открывает смену только себе: чужая смена — это чужие деньги.
        $owner = $data->getUser() ?? $me;

        if ($owner->getId() !== $me->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Открыть смену другому сотруднику может только администратор.');
        }

        return $this->cashSessionOpenService->open($owner, $me);
    }
}
