<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GiftCertificate;
use App\Entity\Order;
use App\Entity\Payment;
use App\Enum\ExtraUnit;
use App\Enum\GiftCertificateStatus;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Message\ExpireUnpaidOrders;
use App\Repository\GiftCertificateRepository;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Service\SiteSettings;
use App\Tests\Support\RecordingHttpClient;
use App\Tests\Support\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Prices with add-ons, promo codes, gift certificates and the online prepayment (demo gateway).
 */
final class SalesTest extends WebTestCase
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function bookingData(array $overrides = []): array
    {
        return array_replace([
            'booking[name]' => 'Алеся Іванова',
            'booking[phone]' => '+375 29 706-75-85',
            'booking[email]' => 'alesia@example.com',
            'booking[startDate]' => (new \DateTimeImmutable('+14 days'))->format('Y-m-d'),
            'booking[durationDays]' => '2',
            'booking[amountOfPeople]' => '4',
            'booking[river]' => 'isloch',
            'booking[package]' => 'all_inclusive',
            'booking[agreeTerms]' => '1',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function book(array $overrides = []): Order
    {
        $crawler = $this->client->request('GET', '/ru/booking?tour=isloch-overnight');
        self::assertResponseIsSuccessful();
        $this->submit($crawler, 'Отправить заявку', $this->bookingData($overrides));
        self::assertResponseRedirects();

        $order = static::getContainer()->get(OrderRepository::class)->findOneBy(['email' => 'alesia@example.com']);
        self::assertInstanceOf(Order::class, $order);

        return $order;
    }

    public function testAddOnsAndPromoCodeAreCharged(): void
    {
        $transfer = $this->factory->extra('Трансфер', 25, ExtraUnit::PER_PERSON);
        $tent = $this->factory->extra('Палатка', 15, ExtraUnit::PER_ITEM, perDay: true);
        $this->factory->extra('Фотограф', 150, ExtraUnit::PER_ORDER);
        $promo = $this->factory->promo('SUMMER10');

        $order = $this->book([
            'booking[extras][e'.$transfer->getId().']' => '1',
            'booking[extras][e'.$tent->getId().']' => '2',
            'booking[promoCode]' => 'summer10',
        ]);

        // 4 × 150 trip + 4 × 25 transfer + 2 tents × 15 × 2 days = 760, −10%.
        self::assertSame(600, $order->getBasePrice());
        self::assertSame(160, $order->getExtrasPrice());
        self::assertSame(76, $order->getDiscount());
        self::assertSame(684, $order->getEstimatedPrice());
        self::assertCount(2, $order->getExtras());
        self::assertSame(1, $this->reload($promo)->getUsedCount());
        self::assertNull($order->getDepositAmount(), 'no prepayment configured');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.price-table', 'Трансфер');
        self::assertSelectorTextContains('.price-table', '684 BYN');

        // The team sees the add-ons and the promo code.
        $telegram = RecordingHttpClient::requestsTo('/sendMessage');
        self::assertStringContainsString('SUMMER10', $telegram[0]['body']);
    }

    public function testUnknownPromoCodeIsRejected(): void
    {
        $crawler = $this->client->request('GET', '/ru/booking?tour=isloch-overnight');
        $this->submit($crawler, 'Отправить заявку', $this->bookingData(['booking[promoCode]' => 'NOPE']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Такого промокода или сертификата нет');
        self::assertSame(0, static::getContainer()->get(OrderRepository::class)->count([]));
    }

    public function testPromoEndpoint(): void
    {
        $this->factory->promo('FIX20', 20, \App\Enum\PromoType::FIXED);
        $gift = $this->factory->gift(150);

        self::assertSame(['valid' => true, 'kind' => 'promo', 'type' => 'fixed', 'value' => 20], $this->checkCode('fix20'));
        self::assertSame(['valid' => true, 'kind' => 'gift', 'balance' => 150], $this->checkCode($gift->getCode()));
        self::assertFalse($this->checkCode('WRONG')['valid']);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCode(string $code): array
    {
        $this->client->request('GET', '/ru/booking/promo?code='.rawurlencode($code));

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    public function testGiftCertificateIsSpentAndRestoredOnCancellation(): void
    {
        $gift = $this->factory->gift(100);

        $order = $this->book(['booking[promoCode]' => strtolower($gift->getCode())]);

        self::assertSame(100, $order->getGiftAmount());
        self::assertSame(500, $order->getEstimatedPrice());
        $gift = $this->reload($gift);
        self::assertSame(0, $gift->getBalance());
        self::assertSame(GiftCertificateStatus::USED, $gift->getStatus());

        $crawler = $this->client->request('GET', '/ru/booking/'.$order->getAccessToken());
        $this->submit($crawler, 'Отменить бронь');

        $restored = static::getContainer()->get(GiftCertificateRepository::class)->find($gift->getId());
        self::assertInstanceOf(GiftCertificate::class, $restored);
        self::assertSame(100, $restored->getBalance(), 'the balance comes back');
        self::assertSame(GiftCertificateStatus::ACTIVE, $restored->getStatus());
    }

    public function testRequiredPrepaymentConfirmsTheBooking(): void
    {
        $this->settings([SiteSettings::PREPAYMENT_PERCENT => 30, SiteSettings::PREPAYMENT_REQUIRED => true]);

        $order = $this->book();
        self::assertSame(180, $order->getDepositAmount(), '30% of 600');
        self::assertEmailHtmlBodyContains($this->emailWithSubject('Заявка на сплав принята'), 'Чтобы подтвердить его, внесите предоплату 180 BYN');

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.pay-box', 'Оплатить 180 BYN');

        // Pay on the (demo) payment page.
        $this->submit($crawler, 'Оплатить 180 BYN');
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Тестовая оплата');
        $form = $crawler->filter('button[value=pay]')->form();
        $this->client->submit($form, ['result' => 'pay']);
        self::assertResponseRedirects();
        self::assertEmailHtmlBodyContains($this->emailWithSubject('Оплата получена'), '180 BYN');
        self::assertNotEmpty(array_filter(RecordingHttpClient::requestsTo('/sendMessage'), static fn (array $r): bool => str_contains((string) json_decode($r['body'], true)['text'], 'Оплата 180 BYN')));
        $this->client->followRedirect();
        self::assertResponseRedirects('/ru/booking/'.$order->getAccessToken());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Оплата прошла успешно');

        $order = $this->reload($order);
        self::assertSame(OrderStatus::CONFIRMED, $order->getStatus(), 'a paid prepayment confirms the booking');
        self::assertSame(180, $order->getPaidAmount());
        self::assertSame(0, $order->getDepositDue());

        // Refund from the admin panel.
        $this->client->loginUser($this->factory->admin());
        $crawler = $this->client->request('GET', '/ru/admin/orders/'.$order->getId().'/edit');
        $this->submit($crawler, 'Вернуть', ['amount' => '180']);
        $payment = static::getContainer()->get(PaymentRepository::class)->findOneBy(['order' => $order->getId()]);
        self::assertInstanceOf(Payment::class, $payment);
        self::assertSame(PaymentStatus::REFUNDED, $payment->getStatus());
        self::assertSame(0, $this->reload($order)->getPaidAmount());
    }

    public function testDeclinedPaymentKeepsTheBookingOpen(): void
    {
        $this->settings([SiteSettings::PREPAYMENT_PERCENT => 50]);
        $order = $this->book();

        $crawler = $this->client->request('GET', '/ru/booking/'.$order->getAccessToken());
        $this->submit($crawler, 'Оплатить 300 BYN');
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('button[value=decline]')->form(), ['result' => 'decline']);
        $this->client->followRedirect();
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'Оплата не прошла');
        $order = $this->reload($order);
        self::assertSame(OrderStatus::NEW, $order->getStatus());
        self::assertSame(300, $order->getDepositDue());
    }

    public function testUnpaidBookingExpires(): void
    {
        $this->settings([SiteSettings::PREPAYMENT_PERCENT => 30, SiteSettings::PREPAYMENT_REQUIRED => true, SiteSettings::PREPAYMENT_HOLD_HOURS => 24]);
        $order = $this->book();
        $fresh = $this->factory->order('+20 days');
        $fresh->setDepositAmount(50);
        $this->em->createQuery('UPDATE App\Entity\Order o SET o.createdAt = :old WHERE o.id = :id')
            ->setParameters(['old' => new \DateTimeImmutable('-25 hours'), 'id' => $order->getId()])
            ->execute();
        $this->em->flush();

        static::getContainer()->get(MessageBusInterface::class)->dispatch(new ExpireUnpaidOrders());

        self::assertSame(OrderStatus::CANCELLED, $this->reload($order)->getStatus());
        self::assertSame(OrderStatus::NEW, $this->reload($fresh)->getStatus(), 'still within the payment window');
        self::assertEmailHtmlBodyContains($this->emailWithSubject('отменена'), 'предоплата по брони не поступила');
    }

    public function testGiftCertificateIsBoughtOnline(): void
    {
        $crawler = $this->client->request('GET', '/ru/gift');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('script[type="application/ld+json"]');

        $this->submit($crawler, 'Перейти к оплате', [
            'gift_certificate[amount]' => '250',
            'gift_certificate[recipientName]' => 'Сергей',
            'gift_certificate[message]' => 'С днём рождения!',
            'gift_certificate[buyerName]' => 'Ольга',
            'gift_certificate[buyerEmail]' => 'Olga@Example.com',
            'gift_certificate[agreeTerms]' => '1',
        ]);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('button[value=pay]')->form(), ['result' => 'pay']);

        $gift = static::getContainer()->get(GiftCertificateRepository::class)->findOneBy(['buyerEmail' => 'olga@example.com']);
        self::assertInstanceOf(GiftCertificate::class, $gift);
        self::assertSame(GiftCertificateStatus::ACTIVE, $gift->getStatus());
        self::assertSame(250, $gift->getBalance());

        $email = $this->emailWithSubject('подарочный сертификат');
        self::assertEmailHtmlBodyContains($email, $gift->getCode());
        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertInstanceOf(DataPart::class, $attachments[0]);
        self::assertSame('application/pdf', $attachments[0]->getMediaType().'/'.$attachments[0]->getMediaSubtype());
        self::assertStringStartsWith('%PDF', $attachments[0]->getBody());

        $this->client->followRedirect();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.gift-card-amount', '250');

        $this->client->request('GET', '/ru/gift/'.$gift->getToken().'/certificate.pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    public function testDemoPaymentPageIsNotAvailableForOtherProviders(): void
    {
        $this->client->request('GET', '/ru/payment/demo/'.str_repeat('a', 32));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/payment/notify/bepaid', content: '{}');
        self::assertResponseStatusCodeSame(404, 'bePaid is not configured in tests');
    }
}
