<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:phpstan',
    description: 'Run PHPStan static analysis'
)]
class PhpstanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('level', 'l', InputOption::VALUE_OPTIONAL, 'Analysis level (0-8)', '8')
            ->addOption('generate-baseline', null, InputOption::VALUE_NONE, 'Generate baseline file')
            ->addOption('clear-cache', null, InputOption::VALUE_NONE, 'Clear PHPStan cache')
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'Memory limit', '1G');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $level = $input->getOption('level');
        $generateBaseline = $input->getOption('generate-baseline');
        $clearCache = $input->getOption('clear-cache');
        $memoryLimit = $input->getOption('memory-limit');

        if ($clearCache) {
            $io->section('Clearing PHPStan cache...');
            $clearProcess = new Process(['vendor/bin/phpstan', 'clear-result-cache']);
            $clearProcess->run();
            
            if (!$clearProcess->isSuccessful()) {
                $io->error('Failed to clear PHPStan cache');

                return Command::FAILURE;
            }
            $io->success('PHPStan cache cleared');
        }

        $command = ['vendor/bin/phpstan', 'analyse'];
        
        if ($generateBaseline) {
            $command[] = '--generate-baseline';
            $io->section('Generating PHPStan baseline...');
        } else {
            $io->section(sprintf('Running PHPStan analysis (level %s)...', $level));
        }
        
        $command[] = '--level=' . $level;
        $command[] = '--memory-limit=' . $memoryLimit;

        $process = new Process($command);
        $process->setTimeout(300);
        
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($process->isSuccessful()) {
            if ($generateBaseline) {
                $io->success('PHPStan baseline generated successfully!');
            } else {
                $io->success('PHPStan analysis completed without errors!');
            }
            
            return Command::SUCCESS;
        } else {
            $exitCode = $process->getExitCode();
            $exitCode = is_int($exitCode) ? $exitCode : Command::FAILURE;
            
            if ($exitCode === 1) {
                $io->warning('PHPStan found some issues. Check the output above.');
            } else {
                $io->error('PHPStan analysis failed with exit code: ' . $exitCode);
            }
            
            return $exitCode;
        }
    }
}
