<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Weather forecast for one day of a trip.
 */
final class DayForecast
{
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly int $code,
        public readonly int $temperatureMin,
        public readonly int $temperatureMax,
        public readonly int $precipitationProbability,
        public readonly int $windSpeed,
    ) {
    }

    /**
     * Group of the WMO weather code, used for the icon and the translated description.
     */
    public function getKind(): string
    {
        return match (true) {
            0 === $this->code => 'clear',
            $this->code <= 2 => 'partly_cloudy',
            3 === $this->code => 'cloudy',
            \in_array($this->code, [45, 48], true) => 'fog',
            $this->code >= 51 && $this->code <= 57 => 'drizzle',
            ($this->code >= 61 && $this->code <= 67) || ($this->code >= 80 && $this->code <= 82) => 'rain',
            ($this->code >= 71 && $this->code <= 77) || 85 === $this->code || 86 === $this->code => 'snow',
            $this->code >= 95 => 'thunderstorm',
            default => 'cloudy',
        };
    }

    public function getEmoji(): string
    {
        return match ($this->getKind()) {
            'clear' => '☀️',
            'partly_cloudy' => '⛅',
            'cloudy' => '☁️',
            'fog' => '🌫️',
            'drizzle' => '🌦️',
            'rain' => '🌧️',
            'snow' => '🌨️',
            'thunderstorm' => '⛈️',
            default => '☁️',
        };
    }

    public function getIcon(): string
    {
        return match ($this->getKind()) {
            'clear' => 'fa-sun',
            'partly_cloudy' => 'fa-cloud-sun',
            'fog' => 'fa-smog',
            'drizzle' => 'fa-cloud-sun-rain',
            'rain' => 'fa-cloud-showers-heavy',
            'snow' => 'fa-snowflake',
            'thunderstorm' => 'fa-cloud-bolt',
            default => 'fa-cloud',
        };
    }

    public function isRainy(): bool
    {
        return $this->precipitationProbability >= 50 || \in_array($this->getKind(), ['rain', 'drizzle', 'thunderstorm'], true);
    }
}
