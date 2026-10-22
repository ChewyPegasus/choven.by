<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Enum\River;
use App\Model\DayForecast;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Daily forecast for the place of a trip from Open-Meteo (free, no API key, up to 16 days ahead).
 *
 * Never fails: when the service is unreachable the forecast is simply not shown.
 */
class WeatherService
{
    public const MAX_DAYS_AHEAD = 14;

    /** Approximate start areas of the rivers, used when the tour has no route on the map. */
    private const RIVER_COORDINATES = [
        'svisloch' => [53.99, 27.42],
        'isloch' => [54.05, 26.95],
        'berezina' => [54.60, 28.30],
        'neman' => [53.60, 26.05],
        'narochanka' => [54.85, 26.80],
        'stracha' => [55.05, 26.40],
        'saryanka' => [55.85, 27.90],
        'sluch' => [53.02, 27.55],
        'viliya' => [54.49, 26.92],
        'sula' => [53.75, 26.85],
        'usa' => [53.55, 26.95],
        'smerd' => [53.80, 27.05],
    ];
    private const MINSK = [53.90, 27.56];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Forecast for the days of the booking, or [] when it is too far ahead, in the past or unavailable.
     *
     * @return list<DayForecast>
     */
    public function forOrder(Order $order, ?\DateTimeImmutable $today = null): array
    {
        $start = $order->getStartDate();
        $end = $order->getEndDate();
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        if (null === $start || null === $end || $end < $today || $start > $today->modify('+'.self::MAX_DAYS_AHEAD.' days')) {
            return [];
        }

        [$lat, $lng] = $this->coordinates($order);

        return $this->forecast($lat, $lng, max($start, $today), min($end, $today->modify('+'.self::MAX_DAYS_AHEAD.' days')));
    }

    /**
     * @return list<DayForecast>
     */
    public function forecast(float $lat, float $lng, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $key = \sprintf('weather.%.2F.%.2F.%s.%s', $lat, $lng, $from->format('Ymd'), $to->format('Ymd'));

        try {
            /** @var list<array{date: string, code: int, tmin: int, tmax: int, rain: int, wind: int}> $days */
            $days = $this->cache->get(str_replace('-', 'm', $key), function (ItemInterface $item) use ($lat, $lng, $from, $to): array {
                $item->expiresAfter(3 * 3600);

                return $this->fetch($lat, $lng, $from, $to);
            });
        } catch (\Throwable $e) {
            $this->logger->notice('Weather forecast unavailable: {message}', ['message' => $e->getMessage()]);

            return [];
        }

        return array_map(static fn (array $d): DayForecast => new DayForecast(
            new \DateTimeImmutable($d['date']),
            $d['code'],
            $d['tmin'],
            $d['tmax'],
            $d['rain'],
            $d['wind'],
        ), $days);
    }

    /**
     * Start point of the tour route on the map, else the river, else Minsk.
     *
     * @return array{0: float, 1: float}
     */
    public function coordinates(Order $order): array
    {
        $points = $order->getTour()?->getRoute()?->getPoints() ?? [];
        foreach ($points as $point) {
            if ('start' === $point['type']) {
                return [(float) $point['lat'], (float) $point['lng']];
            }
        }
        if ([] !== $points) {
            return [(float) $points[0]['lat'], (float) $points[0]['lng']];
        }

        $river = $order->getRiver() ?? $order->getTour()?->getRiver();

        return null !== $river && River::OTHER !== $river ? self::RIVER_COORDINATES[$river->value] : self::MINSK;
    }

    /**
     * @return list<array{date: string, code: int, tmin: int, tmax: int, rain: int, wind: int}>
     */
    private function fetch(float $lat, float $lng, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $data = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => [
                'latitude' => round($lat, 3),
                'longitude' => round($lng, 3),
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,wind_speed_10m_max',
                'timezone' => 'Europe/Minsk',
                'wind_speed_unit' => 'ms',
                'start_date' => $from->format('Y-m-d'),
                'end_date' => $to->format('Y-m-d'),
            ],
            'timeout' => 5,
        ])->toArray();

        $daily = \is_array($data['daily'] ?? null) ? $data['daily'] : [];
        $days = [];
        foreach (array_values((array) ($daily['time'] ?? [])) as $i => $date) {
            $days[] = [
                'date' => (string) $date,
                'code' => (int) ($daily['weather_code'][$i] ?? 3),
                'tmin' => (int) round((float) ($daily['temperature_2m_min'][$i] ?? 0)),
                'tmax' => (int) round((float) ($daily['temperature_2m_max'][$i] ?? 0)),
                'rain' => (int) ($daily['precipitation_probability_max'][$i] ?? 0),
                'wind' => (int) round((float) ($daily['wind_speed_10m_max'][$i] ?? 0)),
            ];
        }

        return $days;
    }
}
