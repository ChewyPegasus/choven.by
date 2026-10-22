<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\GenerateImageVariants;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stores images uploaded from the admin panel under public/uploads.
 *
 * When GD is available the picture is downscaled and re-encoded to WebP, which strips EXIF data
 * and keeps the landing page light.
 */
class ImageUploader
{
    private const MAX_WIDTH = 1600;
    private const QUALITY = 82;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $bus,
        private readonly ImageVariants $variants,
    ) {
    }

    /**
     * @return string path relative to public/, e.g. "uploads/tours/3f2a….webp"
     */
    public function upload(UploadedFile $file, string $folder): string
    {
        $folder = trim(preg_replace('/[^a-z0-9_\/-]/', '', mb_strtolower($folder)) ?? 'misc', '/');
        $directory = $this->publicDir.'/uploads/'.$folder;
        $this->filesystem->mkdir($directory);

        $name = bin2hex(random_bytes(12));

        $optimized = $this->optimize($file->getPathname(), $directory.'/'.$name.'.webp');
        if ($optimized) {
            $path = 'uploads/'.$folder.'/'.$name.'.webp';
        } else {
            $extension = $file->guessExtension() ?? 'jpg';
            $file->move($directory, $name.'.'.$extension);
            $path = 'uploads/'.$folder.'/'.$name.'.'.$extension;
        }

        // Smaller AVIF/WebP copies for phones are built by the worker.
        try {
            $this->bus->dispatch(new GenerateImageVariants($path));
        } catch (\Throwable $e) {
            $this->logger->notice('Could not queue image variants: {message}', ['message' => $e->getMessage()]);
        }

        return $path;
    }

    public function remove(?string $path): void
    {
        if (null === $path || !str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
            return;
        }

        $this->filesystem->remove($this->publicDir.'/'.$path);
        $this->variants->remove($path);
    }

    private function optimize(string $source, string $target): bool
    {
        if (!\function_exists('imagecreatefromstring') || !\function_exists('imagewebp')) {
            return false;
        }

        try {
            $contents = file_get_contents($source);
            $image = false !== $contents ? @imagecreatefromstring($contents) : false;
            if (false === $image) {
                return false;
            }

            $width = imagesx($image);
            if ($width > self::MAX_WIDTH) {
                $scaled = imagescale($image, self::MAX_WIDTH);
                if (false !== $scaled) {
                    imagedestroy($image);
                    $image = $scaled;
                }
            }

            $result = imagewebp($image, $target, self::QUALITY);
            imagedestroy($image);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->warning('Image optimization failed: {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
