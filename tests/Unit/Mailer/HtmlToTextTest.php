<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mailer;

use App\Mailer\HtmlToText;
use PHPUnit\Framework\TestCase;

final class HtmlToTextTest extends TestCase
{
    public function testConvertsLinksParagraphsAndEntities(): void
    {
        $html = '<html><head><style>p{color:red}</style></head><body>'
            .'<h1>Привет &amp; добро пожаловать</h1><p>Первый<br>абзац</p>'
            .'<p><a href="https://choven.by/ru/booking/abc">Открыть бронь</a></p>'
            .'<ul><li>Один</li><li>Два</li></ul></body></html>';

        $text = HtmlToText::convert($html);

        self::assertStringNotContainsString('color:red', $text);
        self::assertStringContainsString('Привет & добро пожаловать', $text);
        self::assertStringContainsString("Первый\nабзац", $text);
        self::assertStringContainsString('Открыть бронь (https://choven.by/ru/booking/abc)', $text);
        self::assertStringContainsString('• Один', $text);
    }
}
