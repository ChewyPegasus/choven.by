<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GiftCertificate;
use App\Entity\PromoCode;
use App\Repository\GiftCertificateRepository;
use App\Repository\PromoCodeRepository;
use Psr\Clock\ClockInterface;

/**
 * Turns the code typed into the booking form into a promo code or a gift certificate.
 */
class DiscountResolver
{
    public const ERROR_NOT_FOUND = 'promo.error.not_found';
    public const ERROR_EXPIRED = 'promo.error.expired';
    public const ERROR_GIFT_EMPTY = 'promo.error.gift_used';

    public function __construct(
        private readonly PromoCodeRepository $promoCodes,
        private readonly GiftCertificateRepository $giftCertificates,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{promo: PromoCode|null, gift: GiftCertificate|null, error: string|null}
     */
    public function resolve(?string $code): array
    {
        $code = PromoCode::normalizeCode((string) $code);
        $result = ['promo' => null, 'gift' => null, 'error' => null];
        if ('' === $code) {
            return $result;
        }
        if (mb_strlen($code) > 32) {
            return ['error' => self::ERROR_NOT_FOUND] + $result;
        }

        $today = $this->clock->now();

        if (str_starts_with($code, 'GIFT-')) {
            $gift = $this->giftCertificates->findOneByCode($code);
            if (null === $gift) {
                return ['error' => self::ERROR_NOT_FOUND] + $result;
            }

            return $gift->isRedeemable($today)
                ? ['gift' => $gift] + $result
                : ['error' => $gift->getBalance() > 0 ? self::ERROR_EXPIRED : self::ERROR_GIFT_EMPTY] + $result;
        }

        $promo = $this->promoCodes->findOneByCode($code);
        if (null === $promo || !$promo->isActive()) {
            return ['error' => self::ERROR_NOT_FOUND] + $result;
        }

        return $promo->isUsable($today) ? ['promo' => $promo] + $result : ['error' => self::ERROR_EXPIRED] + $result;
    }
}
