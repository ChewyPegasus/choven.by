<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Tests\Support\WebTestCase;

final class BookingTest extends WebTestCase
{
    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_replace([
            'booking[name]' => 'Алеся Іванова',
            'booking[phone]' => '8 029 706 75 85',
            'booking[email]' => 'Alesia@Example.com',
            'booking[startDate]' => (new \DateTimeImmutable('+14 days'))->format('Y-m-d'),
            'booking[durationDays]' => '2',
            'booking[amountOfPeople]' => '4',
            'booking[river]' => 'isloch',
            'booking[package]' => 'all_inclusive',
            'booking[description]' => 'Будут дети',
            'booking[agreeTerms]' => '1',
        ], $overrides);
    }

    public function testGuestCanBookATour(): void
    {
        $crawler = $this->client->request('GET', '/ru/booking?tour=isloch-overnight');
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('booking[durationDays]', '2');

        $this->submit($crawler, 'Отправить заявку', $this->validData());

        $order = static::getContainer()->get(OrderRepository::class)->findOneBy(['email' => 'alesia@example.com']);
        self::assertInstanceOf(Order::class, $order);
        self::assertResponseRedirects('/ru/booking/'.$order->getAccessToken().'?created=1');
        self::assertSame('+375297067585', $order->getPhoneE164());
        self::assertSame(OrderStatus::NEW, $order->getStatus());
        self::assertSame(600, $order->getEstimatedPrice(), '150 BYN per person × 4');
        self::assertSame('isloch-overnight', $order->getTour()?->getSlug());
        self::assertSame('ru', $order->getLocale());

        // Confirmation to the customer (with a calendar invitation) and a notification to the team.
        self::assertEmailCount(2);
        $confirmation = $this->email(0);
        self::assertEmailAddressContains($confirmation, 'To', 'alesia@example.com');
        self::assertEmailSubjectContains($confirmation, $order->getReference());
        self::assertEmailAttachmentCount($confirmation, 1);
        self::assertEmailHtmlBodyContains($confirmation, $order->getAccessToken());
        self::assertEmailTextBodyContains($confirmation, $order->getReference());

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Заявка принята');
        self::assertSelectorTextContains('.booking-reference', $order->getReference());
    }

    public function testValidationErrorsAreShown(): void
    {
        $crawler = $this->client->request('GET', '/ru/booking');
        $this->submit($crawler, 'Отправить заявку', $this->validData([
            'booking[phone]' => '123',
            'booking[startDate]' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d'),
            'booking[amountOfPeople]' => '100',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'корректный номер телефона');
        self::assertSelectorTextContains('body', 'Дата не может быть в прошлом');
        self::assertSelectorTextContains('body', 'Количество участников — от 1 до 50');
        self::assertEmailCount(0);
    }

    public function testCapacityIsEnforced(): void
    {
        $this->factory->order('+20 days', people: 18);

        $crawler = $this->client->request('GET', '/ru/booking');
        $this->submit($crawler, 'Отправить заявку', $this->validData([
            'booking[startDate]' => (new \DateTimeImmutable('+20 days'))->format('Y-m-d'),
            'booking[durationDays]' => '1',
            'booking[amountOfPeople]' => '4',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'свободно только 2 мест');
    }

    public function testHoneypotStopsBots(): void
    {
        $crawler = $this->client->request('GET', '/ru/booking');
        $this->submit($crawler, 'Отправить заявку', $this->validData(['booking[website]' => 'http://spam.example']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, static::getContainer()->get(OrderRepository::class)->count([]));
    }

    public function testGuestCanViewAndCancelBookingWithTheSecretLink(): void
    {
        $order = $this->factory->order('+5 days');

        $crawler = $this->client->request('GET', '/ru/booking/'.$order->getAccessToken());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');

        $this->submit($crawler, 'Отменить бронь');
        self::assertResponseRedirects('/ru/booking/'.$order->getAccessToken());

        $order = $this->reload($order);
        self::assertSame(OrderStatus::CANCELLED, $order->getStatus());
        self::assertEmailCount(2); // status email to the customer + notification to the team

        $this->client->request('GET', '/ru/booking/'.str_repeat('a', 64));
        self::assertResponseStatusCodeSame(404);
    }

    public function testCalendarFile(): void
    {
        $order = $this->factory->order('+5 days', days: 2);

        $this->client->request('GET', '/ru/booking/'.$order->getAccessToken().'/calendar.ics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        $ics = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('BEGIN:VEVENT', $ics);
        self::assertStringContainsString('DTSTART;VALUE=DATE:'.$order->getStartDate()?->format('Ymd'), $ics);
        self::assertStringContainsString('DTEND;VALUE=DATE:'.$order->getStartDate()?->modify('+2 days')->format('Ymd'), $ics);
    }

    public function testAvailabilityEndpoint(): void
    {
        $this->factory->order('+3 days', people: 20);
        $day = (new \DateTimeImmutable('+3 days'))->format('Y-m-d');

        $this->client->request('GET', '/ru/booking/availability?from='.(new \DateTimeImmutable('today'))->format('Y-m-d'));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(20, $data['capacity']);
        self::assertSame(['free' => 0, 'blocked' => false], $data['days'][$day]);
    }

    public function testReviewAfterCompletedTrip(): void
    {
        $order = $this->factory->order('-10 days', status: OrderStatus::COMPLETED);

        $crawler = $this->client->request('GET', '/ru/booking/'.$order->getAccessToken().'/review');
        self::assertResponseIsSuccessful();
        $this->submit($crawler, 'Отправить отзыв', [
            'review[rating]' => '5',
            'review[text]' => 'Отличный сплав, всё понравилось!',
            'review[authorName]' => 'Алеся',
        ]);
        self::assertResponseRedirects('/ru/booking/'.$order->getAccessToken());
        self::assertEmailCount(1);

        // Second attempt is refused.
        $this->client->request('GET', '/ru/booking/'.$order->getAccessToken().'/review');
        self::assertResponseRedirects('/ru/booking/'.$order->getAccessToken());
    }

    public function testContactRequest(): void
    {
        $crawler = $this->client->request('GET', '/ru/contact');
        $this->submit($crawler, 'Жду звонка', [
            'contact_request[name]' => 'Пётр',
            'contact_request[phone]' => '+375 33 111-22-33',
            'contact_request[message]' => 'Хотим корпоратив на 30 человек',
            'contact_request[agreeTerms]' => '1',
        ]);

        self::assertResponseRedirects('/ru/#contact');
        self::assertEmailCount(1);
    }
}
