<?php

namespace App\Command;

use App\Component\User\PasswordGenerator;
use App\Component\User\UserFactory;
use App\Component\User\UserManager;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ask:users:create',
    description: 'Creates a user directly (bypassing the API, which requires an existing ROLE_ADMIN) — mainly for bootstrapping the very first admin on a fresh install',
)]
class AskUsersCreateCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserFactory $userFactory,
        private UserManager $userManager,
        private PasswordGenerator $passwordGenerator,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $questionHelper = $this->getHelper('question');
        $emailQuestion = new Question('Email: ');
        $roleQuestion = new Question(sprintf('Role (%s): ', implode(' / ', UserFactory::ALLOWED_ROLES)));
        $firstNameQuestion = new Question('First name: ');
        $lastNameQuestion = new Question('Last name: ');

        $email = '';
        while ($email === '' || $this->userRepository->findOneByEmail($email) !== null) {
            $email = (string) $questionHelper->ask($input, $output, $emailQuestion);

            if ($email !== '' && $this->userRepository->findOneByEmail($email) !== null) {
                $io->warning('Email is already taken: ' . $email);
                $email = '';
            }
        }

        $role = '';
        while (!in_array($role, UserFactory::ALLOWED_ROLES, true)) {
            $role = (string) $questionHelper->ask($input, $output, $roleQuestion);

            if (!in_array($role, UserFactory::ALLOWED_ROLES, true)) {
                $io->warning(sprintf('Role "%s" is not allowed. Allowed roles: %s.', $role, implode(', ', UserFactory::ALLOWED_ROLES)));
            }
        }

        $firstName = (string) $questionHelper->ask($input, $output, $firstNameQuestion);
        $lastName = (string) $questionHelper->ask($input, $output, $lastNameQuestion);


        $password = $this->passwordGenerator->generate();

        $user = $this->userFactory->create($email, $password, [$role], $firstName, $lastName);
        $this->userManager->save($user, true);

        $io->success(sprintf('User #%d created: %s / %s', $user->getId(), $email, $password));

        return Command::SUCCESS;
    }
}
