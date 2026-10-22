<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Extra;
use App\Entity\GiftCertificate;
use App\Entity\PromoCode;
use App\Entity\Tour;
use App\Model\PriceQuote;

/**
 * Trip price + add-ons − promo discount − gift certificate, and the online prepayment.
 *
 * The prepayment is only asked for trips with a public price (otherwise the price is agreed by phone).
 */
class PriceCalculator
{
    public function __construct(private readonly SiteSettings $settings)
    {
    }

    /**
     * @param array<int, int> $extraQuantities extra id => quantity (1 = chosen, for per order/per person extras)
     * @param list<Extra>     $availableExtras
     */
    public function quote(
        ?Tour $tour,
        int $people,
        int $days,
        array $extraQuantities,
        array $availableExtras,
        ?PromoCode $promo = null,
        ?GiftCertificate $gift = null,
    ): PriceQuote {
        $people = max(1, $people);
        $days = max(1, $days);
        $base = $tour?->estimatePrice($people);

        $extras = [];
        $extrasTotal = 0;
        foreach ($availableExtras as $extra) {
            $quantity = $extraQuantities[(int) $extra->getId()] ?? 0;
            $total = $extra->priceFor($quantity, $people, $days);
            if ($total > 0) {
                $extras[] = ['extra' => $extra, 'quantity' => $extra->getUnit()->hasQuantity() ? min($quantity, $extra->getMaxQuantity()) : 1, 'total' => $total];
                $extrasTotal += $total;
            }
        }

        if (null === $base) {
            // No public price: add-ons are listed, but the total (and any discount) is agreed by phone.
            return new PriceQuote(null, $extras, $extrasTotal, 0, 0, null, null);
        }

        $subtotal = $base + $extrasTotal;
        $discount = $promo?->discountFor($subtotal) ?? 0;
        $giftAmount = null !== $gift ? min($gift->getBalance(), $subtotal - $discount) : 0;
        $total = $subtotal - $discount - $giftAmount;

        $deposit = null;
        $percent = $this->settings->getPrepaymentPercent();
        if ($percent > 0 && $total > 0) {
            $deposit = (int) ceil($total * $percent / 100);
        }

        return new PriceQuote($base, $extras, $extrasTotal, $discount, $giftAmount, $total, $deposit);
    }
}
