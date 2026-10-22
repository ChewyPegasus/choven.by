<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Localized;
use App\Repository\SettingRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Typed access to admin-editable settings. Values from the database override the defaults
 * configured in services.yaml (which in turn come from environment variables).
 *
 * The values are memoized per request; reset() (called between requests by long-running
 * workers such as FrankenPHP and Messenger) makes the next request read fresh settings.
 */
class SiteSettings implements ResetInterface
{
    private const CACHE_KEY = 'app.site_settings';

    public const PHONE = 'phone';
    public const EMAIL = 'email';
    public const INSTAGRAM = 'instagram';
    public const TELEGRAM = 'telegram';
    public const NOTIFICATION_EMAIL = 'notification_email';
    public const DAILY_CAPACITY = 'daily_capacity';
    public const BOOKING_ENABLED = 'booking_enabled';
    public const BLOCKED_DATES = 'blocked_dates';
    public const ANNOUNCEMENT = 'announcement';
    public const YANDEX_METRIKA = 'yandex_metrika_id';
    public const GOOGLE_ANALYTICS = 'google_analytics_id';
    public const PREPAYMENT_PERCENT = 'prepayment_percent';
    public const PREPAYMENT_REQUIRED = 'prepayment_required';
    public const PREPAYMENT_HOLD_HOURS = 'prepayment_hold_hours';
    public const FREE_CHANGE_DAYS = 'free_change_days';
    public const GIFT_CERTIFICATES_ENABLED = 'gift_certificates_enabled';
    public const ADMIN_2FA_REQUIRED = 'admin_2fa_required';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(
        private readonly SettingRepository $repository,
        private readonly CacheInterface $cache,
        #[Autowire('%app.settings_defaults%')]
        private readonly array $defaults,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (null === $this->values) {
            $stored = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(3600);

                return $this->repository->findAllAsMap();
            });

            $this->values = array_replace($this->defaults, array_intersect_key($stored, $this->defaults));
        }

        return $this->values;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function save(array $values): void
    {
        $this->repository->saveMap(array_intersect_key($values, $this->defaults));
        $this->cache->delete(self::CACHE_KEY);
        $this->values = null;
    }

    public function getPhone(): string
    {
        return (string) $this->all()[self::PHONE];
    }

    /**
     * Phone number reduced to "+" and digits, for tel: links.
     */
    public function getPhoneLink(): string
    {
        return preg_replace('/[^\d+]/', '', $this->getPhone()) ?? '';
    }

    public function getEmail(): string
    {
        return (string) $this->all()[self::EMAIL];
    }

    public function getInstagram(): ?string
    {
        return $this->nullableString(self::INSTAGRAM);
    }

    public function getTelegram(): ?string
    {
        return $this->nullableString(self::TELEGRAM);
    }

    public function getNotificationEmail(): string
    {
        return $this->nullableString(self::NOTIFICATION_EMAIL) ?? $this->getEmail();
    }

    /**
     * Maximum number of people on the water per day, 0 means unlimited.
     */
    public function getDailyCapacity(): int
    {
        return max(0, (int) $this->all()[self::DAILY_CAPACITY]);
    }

    public function isBookingEnabled(): bool
    {
        return (bool) $this->all()[self::BOOKING_ENABLED];
    }

    /**
     * @return list<string> dates in Y-m-d format
     */
    public function getBlockedDates(): array
    {
        $value = $this->all()[self::BLOCKED_DATES];

        return \is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    public function isDateBlocked(\DateTimeInterface $date): bool
    {
        return \in_array($date->format('Y-m-d'), $this->getBlockedDates(), true);
    }

    /**
     * @return array<string, string>
     */
    public function getAnnouncements(): array
    {
        $value = $this->all()[self::ANNOUNCEMENT];

        return \is_array($value) ? Localized::normalize($value) : [];
    }

    public function getAnnouncement(?string $locale): ?string
    {
        $text = Localized::pick($this->getAnnouncements(), $locale);

        return '' === $text ? null : $text;
    }

    public function getYandexMetrikaId(): ?string
    {
        return $this->nullableString(self::YANDEX_METRIKA);
    }

    public function getGoogleAnalyticsId(): ?string
    {
        return $this->nullableString(self::GOOGLE_ANALYTICS);
    }

    public function hasAnalytics(): bool
    {
        return null !== $this->getYandexMetrikaId() || null !== $this->getGoogleAnalyticsId();
    }

    /**
     * Share of the price paid online when booking, 0–100 (0 = no prepayment).
     */
    public function getPrepaymentPercent(): int
    {
        return max(0, min(100, (int) $this->all()[self::PREPAYMENT_PERCENT]));
    }

    /**
     * Whether the booking is confirmed only after the prepayment (otherwise paying online is optional).
     */
    public function isPrepaymentRequired(): bool
    {
        return $this->getPrepaymentPercent() > 0 && (bool) $this->all()[self::PREPAYMENT_REQUIRED];
    }

    public function getPrepaymentHoldHours(): int
    {
        return max(1, (int) $this->all()[self::PREPAYMENT_HOLD_HOURS]);
    }

    /**
     * Customers can cancel with a refund and reschedule online until this many days before the trip.
     */
    public function getFreeChangeDays(): int
    {
        return max(0, (int) $this->all()[self::FREE_CHANGE_DAYS]);
    }

    public function isGiftCertificatesEnabled(): bool
    {
        return (bool) $this->all()[self::GIFT_CERTIFICATES_ENABLED];
    }

    public function isAdmin2faRequired(): bool
    {
        return (bool) $this->all()[self::ADMIN_2FA_REQUIRED];
    }

    public function reset(): void
    {
        $this->values = null;
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->all()[$key] ?? null;

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
