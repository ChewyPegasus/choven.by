<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EmailRetryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:email:retry',
    description: 'Process email retry queue'
)]
class EmailRetryCommand extends Command
{
    public function __construct(
        private readonly EmailRetryService $emailRetryService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command processes the email retry queue and attempts to resend failed emails');
    }

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