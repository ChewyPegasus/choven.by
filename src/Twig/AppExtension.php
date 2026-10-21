<?php

declare(strict_types=1);

namespace App\Twig;

use App\Model\Localized;
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
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_exists', $this->assetExists(...)),
            new TwigFunction('image_url', $this->imageUrl(...)),
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
