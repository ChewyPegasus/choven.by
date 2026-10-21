<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:role',
    description: 'Grants or revokes administrator rights: app:user:role user@example.com admin|user',
    aliases: ['app:user:make-admin'],
)]
final class UserRoleCommand extends Command
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email of an existing user')
            ->addArgument('role', InputArgument::OPTIONAL, '"admin" or "user"', 'admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $role = (string) $input->getArgument('role');

        if (!\in_array($role, ['admin', 'user'], true)) {
            $io->error('Role must be "admin" or "user".');

            return Command::INVALID;
        }

        $user = $this->users->findOneByEmail($email);
        if (null === $user) {
            $io->error(\sprintf('User "%s" not found.', $email));

            return Command::FAILURE;
        }

        if ('user' === $role && $user->isAdmin() && $this->users->countAdmins() <= 1) {
            $io->error('This is the last administrator; create another one first.');

            return Command::FAILURE;
        }

        $user->setAdmin('admin' === $role)->setIsVerified(true);
        $this->users->save($user);
        $io->success(\sprintf('"%s" is now %s.', $email, 'admin' === $role ? 'an administrator' : 'a regular user'));

        return Command::SUCCESS;
    }
}
