<?php

declare(strict_types=1);

namespace App\Twig;

use App\Model\Localized;
use App\Service\ImageVariants;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ImageVariants $imageVariants,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_exists', $this->assetExists(...)),
            new TwigFunction('image_url', $this->imageUrl(...)),
            new TwigFunction('picture', $this->picture(...), ['is_safe' => ['html']]),
            new TwigFunction('image_preload', $this->imagePreload(...), ['is_safe' => ['html']]),
            new TwigFunction('locale_url', $this->localeUrl(...)),
            new TwigFunction('supported_locales', static fn (): array => Localized::LOCALES),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('localized', $this->localized(...)),
            new TwigFilter('byn', static fn (?int $amount): string => null === $amount ? '' : number_format($amount, 0, ',', ' ').' BYN'),
        ];
    }

    public function assetExists(string $logicalPath): bool
    {
        return null !== $this->assetMapper->getAsset($logicalPath);
    }

    /**
     * Resolves an image reference stored in the database: uploaded files live in public/uploads,
     * anything else is looked up in the asset mapper. Returns null when the image is missing,
     * so templates can show a placeholder instead of a broken picture.
     */
    public function imageUrl(?string $path): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        if (str_starts_with($path, 'uploads/')) {
            return '/'.$path;
        }

        return $this->assetMapper->getPublicPath($path);
    }

    /**
     * Responsive <picture> with AVIF/WebP sources in several widths and the real width/height
     * (no layout shift). Returns '' when the image is missing, so templates can show a placeholder.
     *
     * Options: sizes (default "100vw"), class, loading ("lazy" by default, "eager" for the first screen),
     * fetchpriority, width/height (override).
     *
     * @param array<string, string|int|null> $options
     */
    public function picture(?string $path, string $alt = '', array $options = []): string
    {
        if (null === $path || '' === $path) {
            return '';
        }

        $info = $this->imageVariants->describe($path);
        if (null === $info) {
            return '';
        }

        $sizes = (string) ($options['sizes'] ?? '100vw');
        $loading = (string) ($options['loading'] ?? 'lazy');
        $esc = static fn (string|int $value): string => htmlspecialchars((string) $value, \ENT_QUOTES);

        $html = '<picture>';
        foreach ($info['sources'] as $type => $srcset) {
            $html .= \sprintf('<source type="%s" srcset="%s" sizes="%s">', $esc($type), $esc($srcset), $esc($sizes));
        }

        $attributes = ['src' => $info['src'], 'alt' => $alt];
        $width = $options['width'] ?? $info['width'];
        $height = $options['height'] ?? $info['height'];
        if (null !== $width && null !== $height) {
            $attributes['width'] = $width;
            $attributes['height'] = $height;
        }
        if ('eager' !== $loading) {
            $attributes['loading'] = 'lazy';
        }
        $attributes['decoding'] = 'async';
        if (isset($options['fetchpriority'])) {
            $attributes['fetchpriority'] = $options['fetchpriority'];
        }
        if (isset($options['class'])) {
            $attributes['class'] = $options['class'];
        }

        $html .= '<img';
        foreach ($attributes as $name => $value) {
            $html .= ' '.$name.'="'.$esc((string) $value).'"';
        }

        return $html.'></picture>';
    }

    /**
     * <link rel="preload"> for the main picture of the page (Largest Contentful Paint).
     */
    public function imagePreload(?string $path, string $sizes = '100vw'): string
    {
        $info = null !== $path && '' !== $path ? $this->imageVariants->describe($path) : null;
        if (null === $info) {
            return '';
        }

        $esc = static fn (string $value): string => htmlspecialchars($value, \ENT_QUOTES);
        foreach (['image/avif', 'image/webp'] as $type) {
            if (isset($info['sources'][$type])) {
                return \sprintf('<link rel="preload" as="image" type="%s" imagesrcset="%s" imagesizes="%s" fetchpriority="high">', $type, $esc($info['sources'][$type]), $esc($sizes));
            }
        }

        return \sprintf('<link rel="preload" as="image" href="%s" fetchpriority="high">', $esc($info['src']));
    }

    /**
     * @param array<string, string|null>|null $values
     */
    public function localized(?array $values, ?string $locale = null): string
    {
        $locale ??= $this->requestStack->getCurrentRequest()?->getLocale();

        return Localized::pick($values ?? [], $locale);
    }

    /**
     * The current page in another language (used by the language switcher and hreflang links).
     */
    public function localeUrl(string $locale, bool $absolute = false): string
    {
        $request = $this->requestStack->getMainRequest();
        $route = $request?->attributes->get('_route');
        $type = $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH;

        if (null === $request || !\is_string($route) || str_starts_with($route, '_')) {
            return $this->urlGenerator->generate('app_main', ['_locale' => $locale], $type);
        }

        $params = (array) $request->attributes->get('_route_params', []);
        if (!\array_key_exists('_locale', $params)) {
            return $this->urlGenerator->generate('app_main', ['_locale' => $locale], $type);
        }

        $params['_locale'] = $locale;

        try {
            return $this->urlGenerator->generate($route, $params + $request->query->all(), $type);
        } catch (\Throwable) {
            return $this->urlGenerator->generate('app_main', ['_locale' => $locale], $type);
        }
    }
}
