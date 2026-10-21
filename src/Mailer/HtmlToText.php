<?php

declare(strict_types=1);

namespace App\Mailer;

/**
 * Minimal HTML → plain text conversion for the text/plain part of emails.
 */
final class HtmlToText
{
    public static function convert(string $html): string
    {
        // Drop invisible parts first.
        $text = preg_replace('#<(head|style|script)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        // Keep link targets: <a href="url">label</a> → "label (url)".
        $text = preg_replace_callback(
            '#<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            static function (array $m): string {
                $label = trim(strip_tags($m[3]));
                $url = html_entity_decode($m[2]);

                return '' === $label || $label === $url ? $url : $label.' ('.$url.')';
            },
            $text,
        ) ?? $text;

        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|h[1-6]|tr|li|table)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<li\b[^>]*>#i', '• ', $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $lines = array_map('trim', explode("\n", $text));
        $text = implode("\n", $lines);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
