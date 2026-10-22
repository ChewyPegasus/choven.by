<?php

declare(strict_types=1);

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * `{{ text|markdown }}` for blog posts and FAQ answers written in the admin panel.
 * Raw HTML is escaped and javascript: links are dropped, so the output is safe to print.
 */
final class MarkdownExtension extends AbstractExtension
{
    private ?GithubFlavoredMarkdownConverter $converter = null;

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', $this->toHtml(...), ['is_safe' => ['html']]),
            new TwigFilter('markdown_text', $this->toText(...)),
        ];
    }

    public function toHtml(?string $markdown): string
    {
        if (null === $markdown || '' === trim($markdown)) {
            return '';
        }

        $this->converter ??= new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'renderer' => ['soft_break' => "<br>\n"],
        ]);

        return $this->converter->convert($markdown)->getContent();
    }

    /**
     * Plain text (for meta descriptions and structured data).
     */
    public function toText(?string $markdown): string
    {
        $text = html_entity_decode(strip_tags($this->toHtml($markdown)), \ENT_QUOTES | \ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
