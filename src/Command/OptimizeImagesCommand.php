<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImageVariants;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Builds the responsive AVIF/WebP variants of all photos (assets/images and uploads).
 * Runs during the production image build; safe to run again, existing variants are skipped.
 */
#[AsCommand(name: 'app:images:optimize', description: 'Builds responsive AVIF/WebP variants of all site photos')]
final class OptimizeImagesCommand extends Command
{
    public function __construct(private readonly ImageVariants $variants)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $formats = $this->variants->formats();
        if ([] === $formats) {
            $io->warning('GD with WebP support is not available: nothing to do.');

            return Command::SUCCESS;
        }

        $images = $this->variants->findAllImages();
        $io->writeln(\sprintf('%d images, formats: %s, widths: %s', \count($images), implode(', ', $formats), implode(', ', ImageVariants::WIDTHS)));

        $written = 0;
        foreach ($io->progressIterate($images) as $path) {
            $written += $this->variants->generate($path);
        }

        $io->success(\sprintf('%d variant files written.', $written));

        return Command::SUCCESS;
    }
}
