<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\User\PasswordGenerator;
use App\Component\User\UserFactory;
use App\Component\User\UserManager;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Skips the prompt')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'ROLE_ADMIN or ROLE_SALES')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Skips the prompt')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Skips the prompt')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Use instead of a generated one');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Fully specified on the command line: create without asking anything, so the
        // command can be scripted (seeding an environment, provisioning in CI).
        if ($this->isNonInteractive($input)) {
            return $this->createFromOptions($input, $io);
        }

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

    private function isNonInteractive(InputInterface $input): bool
    {
        return $input->getOption('email') !== null && $input->getOption('role') !== null;
    }

    private function createFromOptions(InputInterface $input, SymfonyStyle $io): int
    {
        $email = (string) $input->getOption('email');
        $role = (string) $input->getOption('role');

        if (!in_array($role, UserFactory::ALLOWED_ROLES, true)) {
            $io->error(sprintf('Role "%s" is not allowed. Allowed roles: %s.', $role, implode(', ', UserFactory::ALLOWED_ROLES)));

            return Command::FAILURE;
        }

        if ($this->userRepository->findOneByEmail($email) !== null) {
            $io->error('Email is already taken: ' . $email);

            return Command::FAILURE;
        }

        $password = (string) ($input->getOption('password') ?? '') ?: $this->passwordGenerator->generate();

        $user = $this->userFactory->create(
            $email,
            $password,
            [$role],
            (string) ($input->getOption('first-name') ?? ''),
            (string) ($input->getOption('last-name') ?? '')
        );
        $this->userManager->save($user, true);

        $io->success(sprintf('User #%d created: %s / %s', $user->getId(), $email, $password));

        return Command::SUCCESS;
    }
}
