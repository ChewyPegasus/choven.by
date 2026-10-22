<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Builds the responsive variants (several widths, AVIF/WebP) of one image in the background.
 */
final class GenerateImageVariants
{
    /**
     * @param string $path "uploads/…" or an AssetMapper path such as "images/slider/view.jpg"
     */
    public function __construct(public readonly string $path)
    {
    }
}
