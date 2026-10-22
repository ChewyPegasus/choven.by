<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\GiftCertificate;
use App\Entity\PromoCode;
use App\Enum\GiftCertificateStatus;
use App\Enum\PromoType;
use PHPUnit\Framework\TestCase;

final class DiscountEntitiesTest extends TestCase
{
    public function testPromoCodeValidity(): void
    {
        $today = new \DateTimeImmutable('2026-07-10 15:00');
        $promo = (new PromoCode())->setCode(' summer10 ')->setValue(10);

        self::assertSame('SUMMER10', $promo->getCode());
        self::assertTrue($promo->isUsable($today));

        $promo->setValidFrom(new \DateTimeImmutable('2026-07-11'));
        self::assertFalse($promo->isUsable($today), 'not yet');

        $promo->setValidFrom(null)->setValidUntil(new \DateTimeImmutable('2026-07-10'));
        self::assertTrue($promo->isUsable($today), 'the last day is included');

        $promo->setValidUntil(null)->setMaxUses(1)->incrementUsage();
        self::assertFalse($promo->isUsable($today), 'all uses are taken');

        $promo->decrementUsage();
        self::assertTrue($promo->isUsable($today));

        $promo->setIsActive(false);
        self::assertFalse($promo->isUsable($today));
    }

    public function testPromoDiscount(): void
    {
        $percent = (new PromoCode())->setType(PromoType::PERCENT)->setValue(15);
        self::assertSame(45, $percent->discountFor(300));

        $fixed = (new PromoCode())->setType(PromoType::FIXED)->setValue(50);
        self::assertSame(50, $fixed->discountFor(300));
        self::assertSame(30, $fixed->discountFor(30), 'never more than the price');
        self::assertSame(0, $fixed->discountFor(0));
    }

    public function testGiftCertificateLifecycle(): void
    {
        $gift = (new GiftCertificate())->setAmount(200);
        self::assertMatchesRegularExpression('/^GIFT-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $gift->getCode());
        self::assertSame(GiftCertificateStatus::PENDING, $gift->getStatus());
        self::assertFalse($gift->isRedeemable(new \DateTimeImmutable()), 'not paid yet');

        $gift->activate();
        self::assertSame(200, $gift->getBalance());
        self::assertTrue($gift->isRedeemable(new \DateTimeImmutable()));
        self::assertEquals((new \DateTimeImmutable('+1 year'))->setTime(0, 0), $gift->getExpiresAt());

        self::assertSame(150, $gift->redeem(150));
        self::assertSame(50, $gift->getBalance());
        self::assertSame(50, $gift->redeem(80), 'only the balance can be taken');
        self::assertSame(GiftCertificateStatus::USED, $gift->getStatus());

        $gift->restore(80);
        self::assertSame(80, $gift->getBalance());
        self::assertSame(GiftCertificateStatus::ACTIVE, $gift->getStatus());

        $gift->restore(1000);
        self::assertSame(200, $gift->getBalance(), 'never more than the amount');

        self::assertFalse($gift->isRedeemable(new \DateTimeImmutable('+2 years')), 'expired');
    }
}
