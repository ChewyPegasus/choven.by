<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\Role;
use App\Exception\UserNotFoundException;
use App\Factory\StyleFactory;
use App\Repository\Interfaces\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony console command to promote an existing user to the admin role.
 *
 * This command takes a user's email as an argument and assigns the
 * 'ROLE_ADMIN' to that user. It provides feedback on whether the user
 * was found, already an admin, or successfully promoted.
 */
#[AsCommand(name: 'app:user:make-admin', description: 'Promote user to admin')]
class CreateAdminCommand extends Command
{
    /**
     * Constructs a new CreateAdminCommand instance.
     *
     * @param UserRepositoryInterface $userRepository The repository for accessing User entities.
     * @param StyleFactory $styleFactory The factory for creating SymfonyStyle instances.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly StyleFactory $styleFactory
    ) {
        parent::__construct();
    }

    /**
     * Configures the command, defining its arguments and options.
     *
     * This command requires a single argument: 'email', which is the email
     * address of the user to be promoted.
     */
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'User email');
    }

    /**
     * Executes the command logic.
     *
     * It retrieves the email argument, attempts to find the user,
     * checks if they are already an admin, adds the admin role,
     * saves the user, and outputs a success or error message.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @return int The command exit code (Command::SUCCESS, Command::FAILURE, or Command::INVALID).
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->styleFactory->create($input, $output);
        $email = $input->getArgument('email');

        try {
            $user = $this->userRepository->findOneByEmail($email);
        } catch (UserNotFoundException $e) {
            $io->error(sprintf('User with email "%s" not found', $email));
            return Command::FAILURE;
        }

        if ($user->isAdmin()) {
            $io->warning(sprintf('User "%s" is already an admin', $email));
            return Command::INVALID;
        }

        $user->addRole(Role::ADMIN);
        $this->userRepository->save($user);

        $io->success(sprintf('User "%s" has been promoted to admin', $email));
        
        return Command::SUCCESS;
    }
}