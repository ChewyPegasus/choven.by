<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Factory\StyleFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:make-admin',
    description: 'Promotes a user to admin role',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly StyleFactory $styleFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'email', 
            InputArgument::REQUIRED, 
            'Email of the user to promote'
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->styleFactory->create($input, $output);
        $email = $input->getArgument('email');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('User with email "%s" not found', $email));
            return Command::FAILURE;
        }

        if ($user->isAdmin()) {
            $io->warning(sprintf('User "%s" is already an admin', $email));
            return Command::INVALID;
        }

        $user->addRole(Role::ADMIN);
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" has been promoted to admin', $email));
        return Command::SUCCESS;
    }
}
