<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds an iCalendar file so customers can add their trip to a calendar in one click.
 */
class IcsGenerator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SiteSettings $settings,
    ) {
    }

    public function forOrder(Order $order, string $url): string
    {
        $locale = $order->getLocale();
        $start = $order->getStartDate() ?? new \DateTimeImmutable('today');
        $endExclusive = ($order->getEndDate() ?? $start)->modify('+1 day');

        $summary = $this->translator->trans('ics.summary', [
            '%river%' => $order->getRiver()?->trans($this->translator, $locale) ?? '',
        ], 'messages', $locale);

        $description = $this->translator->trans('ics.description', [
            '%reference%' => $order->getReference(),
            '%people%' => (string) $order->getAmountOfPeople(),
            '%phone%' => $this->settings->getPhone(),
        ], 'messages', $locale);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Choven.by//Booking//RU',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$order->getReference().'@choven.by',
            'DTSTAMP:'.gmdate('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
            'DTEND;VALUE=DATE:'.$endExclusive->format('Ymd'),
            'SUMMARY:'.$this->escape($summary),
            'DESCRIPTION:'.$this->escape($description."\n".$url),
            'URL:'.$this->escape($url),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /**
     * RFC 5545: lines longer than 75 octets must be folded.
     */
    private function fold(string $line): string
    {
        if (\strlen($line) <= 75) {
            return $line;
        }

        $result = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (\strlen($current.$char) > 74) {
                $result .= $current."\r\n ";
                $current = '';
            }
            $current .= $char;
        }

        return $result.$current;
    }
}
