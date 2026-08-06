<?php

declare(strict_types=1);

namespace App\Controller;

use App\Component\User\Dtos\UserCreatedDto;
use App\Component\User\Exceptions\InvalidRoleException;
use App\Component\User\PasswordGenerator;
use App\Component\User\UserFactory;
use App\Component\User\UserManager;
use App\Controller\Base\AbstractController;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class CreateUserController
 *
 * @package App\Controller
 */
class UserCreateAction extends AbstractController
{
    public function __invoke(
        User $data,
        UserFactory $userFactory,
        UserManager $userManager,
        UserRepository $userRepository,
        PasswordGenerator $passwordGenerator
    ): UserCreatedDto {
        $this->validate($data);

        if ($userRepository->findOneByEmail($data->getEmail())) {
            throw new BadRequestHttpException('Email already taken');
        }

        $roles = array_values(array_diff($data->getRoles(), ['ROLE_USER']));

        if (count($roles) === 0) {
            throw new InvalidRoleException('At least one role is required.');
        }

        foreach ($roles as $role) {
            if (!in_array($role, UserFactory::ALLOWED_ROLES, true)) {
                throw new InvalidRoleException(sprintf(
                    'Role "%s" is not allowed. Allowed roles: %s.',
                    $role,
                    implode(', ', UserFactory::ALLOWED_ROLES)
                ));
            }
        }

        $plainPassword = $passwordGenerator->generate();

        $user = $userFactory->create($data->getEmail(), $plainPassword, $roles);
        $userManager->save($user, true);

        return new UserCreatedDto($user->getId(), $user->getEmail(), $plainPassword, $user->getRoles());
    }
}
