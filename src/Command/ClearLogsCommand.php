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

/**
 * Symfony console command to clear all .log files in the var/log directory.
 *
 * This command empties the content of all files ending with '.log'
 * within the application's 'var/log' directory. It provides feedback
 * on whether files were cleared or if no log directory/files were found.
 */
#[AsCommand(
    name: 'app:logs:clear',
    description: 'Clears all .log files in the var/log directory.',
)]
class ClearLogsCommand extends Command
{
    /**
     * @var string The path to the log directory.
     */
    private string $logDir;

    /**
     * Constructs a new ClearLogsCommand instance.
     *
     * Automatically wires the project directory to locate the log directory.
     *
     * @param string $projectDir The root directory of the Symfony project.
     */
    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->logDir = $projectDir . '/var/log';
        parent::__construct();
    }

    /**
     * Executes the command to clear log files.
     *
     * It checks if the log directory exists, then finds all .log files
     * within it and empties their content. Provides user feedback via SymfonyStyle.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @return int The command exit code (Command::SUCCESS on successful execution).
     */
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