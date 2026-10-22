<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\CompletePastOrders;
use App\Message\PurgeExpiredData;
use App\Message\SendTripReminders;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Runs the scheduled jobs right now (normally the worker runs them on schedule, see src/Schedule.php).
 */
#[AsCommand(name: 'app:jobs:run', description: 'Runs scheduled jobs now: reminders, completing past trips, cleanup')]
final class RunJobsCommand extends Command
{
    private const JOBS = [
        'reminders' => SendTripReminders::class,
        'complete' => CompletePastOrders::class,
        'purge' => PurgeExpiredData::class,
    ];

    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('job', InputArgument::OPTIONAL, 'One of: '.implode(', ', array_keys(self::JOBS)).' (all when omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $job = $input->getArgument('job');

        if (\is_string($job) && !isset(self::JOBS[$job])) {
            $io->error(\sprintf('Unknown job "%s". Available: %s', $job, implode(', ', array_keys(self::JOBS))));

            return Command::INVALID;
        }

        $jobs = \is_string($job) ? [$job => self::JOBS[$job]] : self::JOBS;
        foreach ($jobs as $name => $class) {
            // These messages are not routed to a transport, so they are handled synchronously here.
            $envelope = $this->bus->dispatch(new $class());
            $result = $envelope->last(HandledStamp::class)?->getResult();
            $io->writeln(\sprintf('<info>%s</info>: %s', $name, is_scalar($result) ? (string) $result : 'done'));
        }

        return Command::SUCCESS;
    }
}
