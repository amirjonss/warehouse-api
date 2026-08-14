<?php

declare(strict_types=1);

namespace App\Controller;

use App\Component\User\UserManager;
use App\Controller\Base\AbstractController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserChangePasswordAction extends AbstractController
{
    public function __invoke(
        User $data,
        UserManager $userManager,
        UserPasswordHasherInterface $passwordEncoder,
        EntityManagerInterface $entityManager,
        int $id
    ): User {
        $this->validate($data);

        $newPlainPassword = $data->getPassword();

        $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($data);
        $oldPasswordHash = $originalData['password'] ?? null;

        $referenceUser = (new User())->setPassword($oldPasswordHash ?? '');

        if ($oldPasswordHash === null || !$passwordEncoder->isPasswordValid($referenceUser, $data->getCurrentPassword())) {
            throw new BadRequestHttpException('Current password is incorrect');
        }

        $userManager->hashPassword($data, $newPlainPassword);
        $data->bumpTokenVersion();
        $userManager->save($data, true);

        return $data;
    }
}
