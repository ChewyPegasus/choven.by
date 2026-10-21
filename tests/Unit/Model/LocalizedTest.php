<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Localized;
use PHPUnit\Framework\TestCase;

final class LocalizedTest extends TestCase
{
    public function testPicksRequestedLocale(): void
    {
        self::assertSame('Hello', Localized::pick(['ru' => 'Привет', 'en' => 'Hello'], 'en'));
    }

    public function testFallsBackToRussianThenOtherLocales(): void
    {
        self::assertSame('Привет', Localized::pick(['ru' => 'Привет', 'en' => 'Hello'], 'be'));
        self::assertSame('Hello', Localized::pick(['en' => 'Hello'], 'be'));
        self::assertSame('Hello', Localized::pick(['ru' => '  ', 'en' => 'Hello'], 'ru'));
        self::assertSame('', Localized::pick([], 'ru'));
    }

    public function testNormalizeKeepsOnlyKnownNonEmptyTrimmedValues(): void
    {
        self::assertSame(
            ['ru' => 'Текст', 'en' => 'Text'],
            Localized::normalize(['en' => ' Text ', 'ru' => 'Текст', 'be' => '', 'de' => 'Text', 'xx' => 5]),
        );
    }
}
