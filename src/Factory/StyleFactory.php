<?php
declare(strict_types=1);

namespace App\Factory;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Factory for creating SymfonyStyle instances.
 *
 * This factory provides a simple way to instantiate `SymfonyStyle` objects,
 * which are used for styling console command input and output.
 */
class StyleFactory
{
    /**
     * Creates a new SymfonyStyle instance.
     *
     * @param InputInterface $input The input interface for the console command.
     * @param OutputInterface $output The output interface for the console command.
     * @return SymfonyStyle A new SymfonyStyle object configured with the given input and output.
     */
    public function create(InputInterface $input, OutputInterface $output): SymfonyStyle
    {
        return new SymfonyStyle($input, $output);
    }
}