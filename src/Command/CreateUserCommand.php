<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Creates a verified user account (use --admin for an administrator)',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address used to sign in')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password (asked interactively when omitted)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant administrator rights');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        if (null !== $this->users->findOneByEmail($email)) {
            $io->error(\sprintf('User "%s" already exists. Use app:user:role to change their role.', $email));

            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        if (!\is_string($password) || '' === $password) {
            $password = (string) $io->askHidden('Password');
        }
        if (mb_strlen($password) < RegistrationType::MIN_PASSWORD_LENGTH) {
            $io->error(\sprintf('The password must be at least %d characters long.', RegistrationType::MIN_PASSWORD_LENGTH));

            return Command::INVALID;
        }

        $user = (new User())
            ->setEmail($email)
            ->setName(\is_string($input->getOption('name')) ? $input->getOption('name') : null)
            ->setIsVerified(true)
            ->setAdmin((bool) $input->getOption('admin'));
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error($violation->getPropertyPath().': '.$violation->getMessage());
            }

            return Command::INVALID;
        }

        $this->users->save($user);
        $io->success(\sprintf('%s "%s" created.', $user->isAdmin() ? 'Administrator' : 'User', $user->getEmail()));

        return Command::SUCCESS;
    }
}
