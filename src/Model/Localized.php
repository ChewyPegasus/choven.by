<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Helpers for content stored as a {locale: text} map (tours, routes, route points).
 */
final class Localized
{
    public const DEFAULT_LOCALE = 'ru';

    /** Order matters: it is also the fallback order when a translation is missing. */
    public const LOCALES = ['ru', 'be', 'en'];

    /**
     * @param array<string, string|null> $values
     */
    public static function pick(array $values, ?string $locale = null): string
    {
        $locale ??= self::DEFAULT_LOCALE;
        if (isset($values[$locale]) && '' !== trim((string) $values[$locale])) {
            return (string) $values[$locale];
        }

        foreach (self::LOCALES as $fallback) {
            if (isset($values[$fallback]) && '' !== trim((string) $values[$fallback])) {
                return (string) $values[$fallback];
            }
        }

        return '';
    }

    /**
     * Drops unknown locales and trims values, so only clean data reaches the database.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, string>
     */
    public static function normalize(array $values): array
    {
        $result = [];
        foreach (self::LOCALES as $locale) {
            $value = $values[$locale] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                $result[$locale] = trim($value);
            }
        }

        return $result;
    }

    public static function isSupported(string $locale): bool
    {
        return \in_array($locale, self::LOCALES, true);
    }
}
