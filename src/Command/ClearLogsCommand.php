<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:logs:clear',
    description: 'Clears all .log files in the var/log directory.',
)]
class ClearLogsCommand extends Command
{
    private string $logDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->logDir = $projectDir . '/var/log';
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $finder = new Finder();

        if (!is_dir($this->logDir)) {
            $io->note('Log directory does not exist. Nothing to clear.');
            return Command::SUCCESS;
        }

        $logFiles = $finder->files()->in($this->logDir)->name('*.log');

        if (!$logFiles->hasResults()) {
            $io->note('No log files found to clear.');
            return Command::SUCCESS;
        }

        $clearedCount = 0;
        foreach ($logFiles as $file) {
            file_put_contents($file->getRealPath(), '');
            $clearedCount++;
        }

        $io->success(sprintf('Successfully cleared %d log file(s).', $clearedCount));

        return Command::SUCCESS;
    }
}