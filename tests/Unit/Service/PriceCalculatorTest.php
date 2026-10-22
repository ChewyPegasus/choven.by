<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Extra;
use App\Entity\GiftCertificate;
use App\Entity\PromoCode;
use App\Entity\Tour;
use App\Enum\ExtraUnit;
use App\Enum\PromoType;
use App\Service\PriceCalculator;
use App\Service\SiteSettings;
use PHPUnit\Framework\TestCase;

final class PriceCalculatorTest extends TestCase
{
    private function calculator(int $prepaymentPercent = 0): PriceCalculator
    {
        $settings = $this->createStub(SiteSettings::class);
        $settings->method('getPrepaymentPercent')->willReturn($prepaymentPercent);

        return new PriceCalculator($settings);
    }

    private function tour(int $price, string $unit = Tour::PRICE_PER_PERSON): Tour
    {
        return (new Tour())->setPriceFrom($price)->setPriceUnit($unit);
    }

    private function extra(int $id, int $price, ExtraUnit $unit, bool $perDay = false): Extra
    {
        $extra = (new Extra())->setName(['ru' => 'E'.$id])->setPrice($price)->setUnit($unit)->setPerDay($perDay)->setMaxQuantity(5);
        (new \ReflectionProperty(Extra::class, 'id'))->setValue($extra, $id);

        return $extra;
    }

    public function testTripAndAddOns(): void
    {
        $extras = [
            $this->extra(1, 25, ExtraUnit::PER_PERSON),
            $this->extra(2, 15, ExtraUnit::PER_ITEM, perDay: true),
            $this->extra(3, 150, ExtraUnit::PER_ORDER),
        ];

        // 4 people × 2 days: trip 4×100, transfer 4×25, 2 tents × 15 × 2 days, no photographer.
        $quote = $this->calculator(30)->quote($this->tour(100), 4, 2, [1 => 1, 2 => 2, 3 => 0], $extras);

        self::assertSame(400, $quote->base);
        self::assertSame(100 + 60, $quote->extrasTotal);
        self::assertCount(2, $quote->extras);
        self::assertSame(560, $quote->total);
        self::assertSame(168, $quote->deposit, '30% of 560');
    }

    public function testQuantityIsCappedAndUnknownExtrasAreIgnored(): void
    {
        $quote = $this->calculator()->quote($this->tour(50, Tour::PRICE_PER_GROUP), 3, 1, [7 => 100, 99 => 3], [$this->extra(7, 10, ExtraUnit::PER_ITEM)]);

        self::assertSame(50, $quote->base, 'group price does not depend on the number of people');
        self::assertSame(50, $quote->extrasTotal, 'at most 5 items');
        self::assertNull($quote->deposit);
    }

    public function testPromoAndGiftCertificate(): void
    {
        $promo = (new PromoCode())->setCode('TEN')->setType(PromoType::PERCENT)->setValue(10);
        $gift = (new GiftCertificate())->setAmount(100)->activate();

        $quote = $this->calculator(50)->quote($this->tour(150), 2, 1, [], [], $promo, $gift);

        self::assertSame(30, $quote->discount);
        self::assertSame(100, $quote->giftAmount);
        self::assertSame(170, $quote->total);
        self::assertSame(85, $quote->deposit);
    }

    public function testGiftCanCoverEverything(): void
    {
        $gift = (new GiftCertificate())->setAmount(500)->activate();

        $quote = $this->calculator(30)->quote($this->tour(100), 2, 1, [], [], null, $gift);

        self::assertSame(200, $quote->giftAmount, 'only what is needed is taken');
        self::assertSame(0, $quote->total);
        self::assertNull($quote->deposit, 'nothing left to prepay');
    }

    public function testWithoutPublicPriceNothingIsCalculated(): void
    {
        $promo = (new PromoCode())->setCode('FIX')->setType(PromoType::FIXED)->setValue(20);

        $quote = $this->calculator(30)->quote(null, 2, 1, [1 => 1], [$this->extra(1, 25, ExtraUnit::PER_PERSON)], $promo);

        self::assertNull($quote->total);
        self::assertSame(50, $quote->extrasTotal, 'add-ons are still listed');
        self::assertSame(0, $quote->discount);
        self::assertNull($quote->deposit);
    }
}
