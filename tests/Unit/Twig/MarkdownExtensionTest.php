<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\MarkdownExtension;
use PHPUnit\Framework\TestCase;

final class MarkdownExtensionTest extends TestCase
{
    public function testMarkdownIsRenderedSafely(): void
    {
        $markdown = new MarkdownExtension();
        $html = $markdown->toHtml("## Маршрут\n\n- **Ислочь**\n- [карта](https://choven.by)\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1))");

        self::assertStringContainsString('<h2>Маршрут</h2>', $html);
        self::assertStringContainsString('<strong>Ислочь</strong>', $html);
        self::assertStringContainsString('<a href="https://choven.by">карта</a>', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertSame('Маршрут Ислочь карта', $markdown->toText("## Маршрут\n\n- **Ислочь**\n- [карта](https://choven.by)"));
        self::assertSame('', $markdown->toHtml(null));
    }
}
