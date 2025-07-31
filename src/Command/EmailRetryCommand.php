<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Retry\EmailRetryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

/**
 * Symfony console command for processing the email retry queue.
 *
 * This command triggers the EmailRetryService to attempt resending
 * emails that previously failed to send. It provides console output
 * for the process status and logs any errors encountered.
 */
#[AsCommand(
    name: 'app:email:retry',
    description: 'Process email retry queue'
)]
class EmailRetryCommand extends Command
{
    /**
     * Constructs a new EmailRetryCommand instance.
     *
     * @param EmailRetryService $emailRetryService The service responsible for email retries.
     * @param LoggerInterface $logger The logger instance for recording command activities and errors.
     */
    public function __construct(
        private readonly EmailRetryService $emailRetryService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    /**
     * Configures the command, setting its help message.
     */
    protected function configure(): void
    {
        $this->setHelp('This command processes the email retry queue and attempts to resend failed emails');
    }

    /**
     * Executes the email retry process.
     *
     * It calls the EmailRetryService to process the queue.
     * Outputs messages to the console indicating the start and completion status,
     * and logs any exceptions that occur during the process.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @return int The command exit code (Command::SUCCESS on success, Command::FAILURE on error).
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting email retry process...');
        
        try {
            $this->emailRetryService->processQueue();
            $output->writeln('Email retry process completed successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Error in email retry command', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            
            return Command::FAILURE;
        }
    }
}