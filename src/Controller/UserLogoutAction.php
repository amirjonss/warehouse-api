<?php

declare(strict_types=1);

namespace App\Controller;

use App\Component\User\UserManager;
use App\Controller\Base\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class UserLogoutAction extends AbstractController
{
    public function __invoke(UserManager $userManager): Response
    {
        $user = $this->getUser();
        $user->bumpTokenVersion();
        $userManager->save($user, true);

        return $this->responseEmpty();
    }
}
