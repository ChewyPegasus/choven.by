<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\GenerateImageVariants;
use Psr\Log\LoggerInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Responsive images: every photo is also stored in several widths as AVIF and WebP,
 * so phones download a 480 px WebP/AVIF instead of a 2 MB JPEG.
 *
 * Variants of uploads live next to them (public/uploads/_v/…); variants of the photos in assets/images
 * go to public/media/<content hash>/…, so their URLs change whenever the photo changes and can be cached forever.
 * Missing variants are built in the background (or by `app:images:optimize`); until then the original is shown.
 */
#[AsMessageHandler]
class ImageVariants
{
    public const WIDTHS = [480, 800, 1200, 1600];
    private const WEBP_QUALITY = 80;
    private const AVIF_QUALITY = 55;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly CacheInterface $cache,
        private readonly MessageBusInterface $bus,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Everything a <picture> element needs. Null when the image does not exist.
     *
     * @return array{src: string, width: int|null, height: int|null, sources: array<string, string>}|null
     */
    public function describe(string $path): ?array
    {
        $source = $this->sourceFile($path);
        $src = $this->originalUrl($path);
        if (null === $source || null === $src) {
            return null;
        }

        $mtime = (int) @filemtime($source);
        $key = 'img_'.md5($path.'|'.$mtime.'|'.(int) @filesize($source));

        /** @var array{width: int|null, height: int|null, sources: array<string, string>, complete: bool} $info */
        $info = $this->cache->get($key, function (ItemInterface $item) use ($path, $source): array {
            $size = @getimagesize($source);
            $sources = $this->existingSources($path, \is_array($size) ? $size[0] : null);
            $complete = $this->isComplete($path, \is_array($size) ? $size[0] : null);
            // Until all variants exist, look again soon.
            $item->expiresAfter($complete ? 30 * 86400 : 60);

            return [
                'width' => \is_array($size) ? $size[0] : null,
                'height' => \is_array($size) ? $size[1] : null,
                'sources' => $sources,
                'complete' => $complete,
            ];
        });

        if (!$info['complete']) {
            $this->scheduleGeneration($path, $key);
        }

        return ['src' => $src, 'width' => $info['width'], 'height' => $info['height'], 'sources' => $info['sources']];
    }

    public function __invoke(GenerateImageVariants $message): void
    {
        $this->generate($message->path);
    }

    /**
     * Builds the missing variants of one image; returns how many files were written.
     */
    public function generate(string $path): int
    {
        $source = $this->sourceFile($path);
        if (null === $source || !\function_exists('imagecreatefromstring')) {
            return 0;
        }

        $contents = @file_get_contents($source);
        $image = false !== $contents ? @imagecreatefromstring($contents) : false;
        if (false === $image) {
            return 0;
        }

        $written = 0;
        try {
            $width = imagesx($image);
            foreach ($this->targetWidths($width) as $target) {
                $scaled = $target < $width ? imagescale($image, $target, -1, \IMG_BICUBIC) : $image;
                if (false === $scaled) {
                    continue;
                }
                foreach ($this->formats() as $format) {
                    $file = $this->variantFile($path, $target, $format);
                    if (null === $file || is_file($file)) {
                        continue;
                    }
                    $this->filesystem->mkdir(\dirname($file));
                    $tmp = $file.'.tmp';
                    $ok = 'avif' === $format ? @imageavif($scaled, $tmp, self::AVIF_QUALITY, 6) : @imagewebp($scaled, $tmp, self::WEBP_QUALITY);
                    if ($ok && filesize($tmp) > 0) {
                        $this->filesystem->rename($tmp, $file, true);
                        ++$written;
                    } else {
                        $this->filesystem->remove($tmp);
                    }
                }
                if ($scaled !== $image) {
                    imagedestroy($scaled);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Could not build variants of {path}: {message}', ['path' => $path, 'message' => $e->getMessage()]);
        } finally {
            imagedestroy($image);
        }

        return $written;
    }

    /**
     * Deletes the variants of an upload that is being removed.
     */
    public function remove(string $path): void
    {
        foreach (self::WIDTHS as $width) {
            foreach (['avif', 'webp'] as $format) {
                $file = $this->variantFile($path, $width, $format);
                if (null !== $file) {
                    $this->filesystem->remove($file);
                }
            }
        }
    }

    /**
     * All images that can have variants: uploads and the photos in assets/images.
     *
     * @return list<string>
     */
    public function findAllImages(): array
    {
        $paths = [];
        foreach (['assets/images' => 'images', 'public/uploads' => 'uploads'] as $dir => $prefix) {
            $root = $this->projectDir.'/'.$dir;
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $relative = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));
                if ($file->isFile() && !str_starts_with($relative, '_v/') && preg_match('/\.(jpe?g|png|webp)$/i', $relative)) {
                    $paths[] = $prefix.'/'.$relative;
                }
            }
        }
        sort($paths);

        return $paths;
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        $formats = [];
        if (\function_exists('imageavif') && (gd_info()['AVIF Support'] ?? false)) {
            $formats[] = 'avif';
        }
        if (\function_exists('imagewebp')) {
            $formats[] = 'webp';
        }

        return $formats;
    }

    /**
     * @return list<int>
     */
    private function targetWidths(?int $width): array
    {
        if (null === $width || $width <= 0) {
            return [];
        }

        $widths = array_values(array_filter(self::WIDTHS, static fn (int $w): bool => $w < $width));
        // The largest variant is the original size (capped), so big screens get a sharp picture too.
        $widths[] = min($width, max(self::WIDTHS));

        return array_values(array_unique($widths));
    }

    /**
     * @return array<string, string> MIME type => srcset
     */
    private function existingSources(string $path, ?int $width): array
    {
        $sources = [];
        foreach ($this->formats() as $format) {
            $set = [];
            foreach ($this->targetWidths($width) as $target) {
                $file = $this->variantFile($path, $target, $format);
                if (null !== $file && is_file($file)) {
                    $set[] = $this->variantUrl($path, $target, $format).' '.$target.'w';
                }
            }
            if ([] !== $set) {
                $sources['image/'.$format] = implode(', ', $set);
            }
        }

        return $sources;
    }

    private function isComplete(string $path, ?int $width): bool
    {
        foreach ($this->formats() as $format) {
            foreach ($this->targetWidths($width) as $target) {
                $file = $this->variantFile($path, $target, $format);
                if (null !== $file && !is_file($file)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function scheduleGeneration(string $path, string $key): void
    {
        try {
            // Once per image and minute, however many pages show it.
            $this->cache->get($key.'_queued', function (ItemInterface $item) use ($path): bool {
                $item->expiresAfter(60);
                $this->bus->dispatch(new GenerateImageVariants($path));

                return true;
            });
        } catch (\Throwable $e) {
            $this->logger->notice('Could not queue image variants for {path}: {message}', ['path' => $path, 'message' => $e->getMessage()]);
        }
    }

    private function sourceFile(string $path): ?string
    {
        if (str_contains($path, '..')) {
            return null;
        }

        $file = str_starts_with($path, 'uploads/')
            ? $this->projectDir.'/public/'.$path
            : $this->projectDir.'/assets/'.$path;

        return is_file($file) ? $file : null;
    }

    private function originalUrl(string $path): ?string
    {
        return str_starts_with($path, 'uploads/') ? '/'.$path : $this->assetMapper->getPublicPath($path);
    }

    private function variantFile(string $path, int $width, string $format): ?string
    {
        $relative = $this->variantRelativePath($path, $width, $format);

        return null !== $relative ? $this->projectDir.'/public/'.$relative : null;
    }

    private function variantUrl(string $path, int $width, string $format): string
    {
        return '/'.$this->variantRelativePath($path, $width, $format);
    }

    private function variantRelativePath(string $path, int $width, string $format): ?string
    {
        $base = (string) preg_replace('/\.[a-z0-9]+$/i', '', $path);

        if (str_starts_with($path, 'uploads/')) {
            // Upload names are random and never reused: no hash needed.
            return 'uploads/_v/'.substr($base, \strlen('uploads/')).'-'.$width.'.'.$format;
        }

        $source = $this->sourceFile($path);
        if (null === $source) {
            return null;
        }
        $hash = substr(md5((string) @filemtime($source).'|'.(string) @filesize($source).'|'.$path), 0, 10);

        return 'media/'.$hash.'/'.basename($base).'-'.$width.'.'.$format;
    }
}
