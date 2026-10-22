<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\MessengerSubscription;
use App\Entity\WaitlistEntry;
use App\Enum\MessengerChannel;
use App\Enum\OrderStatus;
use App\Message\RecordHeartbeat;
use App\Message\SendTripReminders;
use App\Messenger\SubscriptionLinker;
use App\Repository\MessengerSubscriptionRepository;
use App\Repository\WaitlistEntryRepository;
use App\Service\BookingService;
use App\Tests\Support\RecordingHttpClient;
use App\Tests\Support\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Waitlist, self-service rescheduling, messenger notifications and the weather in reminders.
 */
final class CustomerServiceTest extends WebTestCase
{
    public function testWaitlistIsNotifiedWhenPlacesBecomeFree(): void
    {
        $date = new \DateTimeImmutable('+20 days');
        $blocking = $this->factory->order('+20 days', people: 18);

        $crawler = $this->client->request('GET', '/ru/booking');
        $this->submit($crawler, 'Отправить заявку', [
            'booking[name]' => 'Алеся',
            'booking[phone]' => '+375297067585',
            'booking[email]' => 'alesia@example.com',
            'booking[startDate]' => $date->format('Y-m-d'),
            'booking[durationDays]' => '1',
            'booking[amountOfPeople]' => '4',
            'booking[river]' => 'isloch',
            'booking[package]' => 'minimum',
            'booking[agreeTerms]' => '1',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#waitlist');
        self::assertInputValueSame('waitlist[people]', '4');

        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->selectButton('Встать в лист ожидания')->form(), ['waitlist[agreeTerms]' => '1']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Вы в листе ожидания');
        self::assertEmailSubjectContains($this->emailWithSubject('листе ожидания'), $date->format('d.m.Y'));

        $entry = static::getContainer()->get(WaitlistEntryRepository::class)->findOneBy(['email' => 'alesia@example.com']);
        self::assertInstanceOf(WaitlistEntry::class, $entry);
        self::assertNull($entry->getNotifiedAt());

        // The other group cancels: places become free and the waiting customer gets an email.
        static::getContainer()->get(BookingService::class)->cancelByCustomer($this->reload($blocking));
        $email = $this->emailWithSubject('Освободились места');
        self::assertEmailAddressContains($email, 'To', 'alesia@example.com');
        self::assertEmailHtmlBodyContains($email, 'people=4');
        self::assertNotNull($this->reload($entry)->getNotifiedAt());
    }

    public function testCustomerReschedulesOnline(): void
    {
        $order = $this->factory->order('+10 days', status: OrderStatus::CONFIRMED);
        $newDate = new \DateTimeImmutable('+12 days');

        $crawler = $this->client->request('GET', '/ru/booking/'.$order->getAccessToken());
        self::assertSelectorTextContains('body', 'Бесплатно перенести или отменить');
        $crawler = $this->client->click($crawler->selectLink('Перенести дату')->link());
        $this->submit($crawler, 'Перенести', ['reschedule[startDate]' => $newDate->format('Y-m-d')]);
        self::assertResponseRedirects('/ru/booking/'.$order->getAccessToken());

        $email = $this->emailWithSubject('перенесена');
        self::assertEmailAttachmentCount($email, 1);
        self::assertNotEmpty(RecordingHttpClient::requestsTo('/sendMessage'), 'the team is told');

        $order = $this->reload($order);
        self::assertEquals($newDate->setTime(0, 0), $order->getStartDate());
        self::assertSame(1, $order->getRescheduleCount());
        self::assertNull($order->getReminderSentAt());
    }

    public function testReschedulingIsRefusedTooLateOrOnFullDates(): void
    {
        $soon = $this->factory->order('+2 days');
        $this->client->request('GET', '/ru/booking/'.$soon->getAccessToken().'/reschedule');
        self::assertResponseRedirects('/ru/booking/'.$soon->getAccessToken());

        $order = $this->factory->order('+10 days', people: 5);
        $this->factory->order('+15 days', people: 18);
        $crawler = $this->client->request('GET', '/ru/booking/'.$order->getAccessToken().'/reschedule');
        $this->submit($crawler, 'Перенести', ['reschedule[startDate]' => (new \DateTimeImmutable('+15 days'))->format('Y-m-d')]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'не хватает');
    }

    public function testTelegramSubscriptionAndNotifications(): void
    {
        $order = $this->factory->order('+10 days');
        $payload = static::getContainer()->get(SubscriptionLinker::class)->payloadForOrder($order);

        $crawler = $this->client->request('GET', '/ru/booking/'.$order->getAccessToken());
        self::assertSame('https://t.me/choven_test_bot?start='.$payload, $crawler->filter('.btn-telegram')->attr('href'));

        $this->client->request('POST', '/webhook/telegram', server: ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'wrong'], content: '{}');
        self::assertResponseStatusCodeSame(403);

        $reply = $this->telegram(['message' => ['chat' => ['id' => 555, 'type' => 'private', 'first_name' => 'Ivan'], 'from' => ['language_code' => 'ru'], 'text' => '/start '.$payload]]);
        self::assertSame('sendMessage', $reply['method']);
        self::assertSame('555', (string) $reply['chat_id']);
        self::assertStringContainsString($order->getReference(), $reply['text']);

        $subscription = static::getContainer()->get(MessengerSubscriptionRepository::class)->findOneBy(['externalId' => '555']);
        self::assertInstanceOf(MessengerSubscription::class, $subscription);
        self::assertSame(MessengerChannel::TELEGRAM, $subscription->getChannel());

        // A forged payload is refused.
        $reply = $this->telegram(['message' => ['chat' => ['id' => 556, 'type' => 'private'], 'text' => '/start o'.$order->getId().'x0000000000000000']]);
        self::assertStringContainsString('Ссылка устарела', $reply['text']);

        // Status change → message to the customer's chat with a button to the booking.
        RecordingHttpClient::reset();
        $this->client->loginUser($this->factory->admin());
        $crawler = $this->client->request('GET', '/ru/admin/orders/'.$order->getId().'/edit');
        $this->submit($crawler, 'Сохранить', ['order_admin[status]' => 'confirmed', 'order_admin[notifyCustomer]' => '1']);

        $toCustomer = array_values(array_filter(RecordingHttpClient::requestsTo('/sendMessage'), static fn (array $r): bool => '555' === (string) (json_decode($r['body'], true)['chat_id'] ?? '')));
        self::assertCount(1, $toCustomer);
        self::assertStringContainsString('подтверждена', (string) json_decode($toCustomer[0]['body'], true)['text']);

        // /stop switches it off.
        $this->telegram(['message' => ['chat' => ['id' => 555, 'type' => 'private'], 'text' => '/stop']]);
        self::assertFalse($this->reload($subscription)->isActive());
    }

    public function testViberSubscription(): void
    {
        RecordingHttpClient::respond('chatapi.viber.com', ['status' => 0, 'status_message' => 'ok']);
        $order = $this->factory->order('+10 days');
        $payload = static::getContainer()->get(SubscriptionLinker::class)->payloadForOrder($order);

        $this->client->request('POST', '/webhook/viber', server: ['HTTP_X_VIBER_CONTENT_SIGNATURE' => 'bad'], content: '{}');
        self::assertResponseStatusCodeSame(403);

        $welcome = $this->viber(['event' => 'conversation_started', 'user' => ['id' => 'v-1', 'name' => 'Ivan', 'language' => 'ru'], 'context' => $payload]);
        self::assertSame('link:'.$payload, $welcome['keyboard']['Buttons'][0]['ActionBody']);

        $this->viber(['event' => 'message', 'sender' => ['id' => 'v-1', 'name' => 'Ivan'], 'message' => ['type' => 'text', 'text' => 'link:'.$payload]]);
        self::assertNotNull(static::getContainer()->get(MessengerSubscriptionRepository::class)->findOneBy(['externalId' => 'v-1', 'isActive' => true]));
        $sent = RecordingHttpClient::requestsTo('chatapi.viber.com/pa/send_message');
        self::assertCount(1, $sent);
        self::assertSame('v-1', json_decode($sent[0]['body'], true)['receiver']);
    }

    public function testReminderContainsTheWeatherForecast(): void
    {
        $start = new \DateTimeImmutable('+2 days');
        RecordingHttpClient::respond('api.open-meteo.com', ['daily' => [
            'time' => [$start->format('Y-m-d')],
            'weather_code' => [61],
            'temperature_2m_max' => [22.6],
            'temperature_2m_min' => [13.2],
            'precipitation_probability_max' => [70],
            'wind_speed_10m_max' => [4.4],
        ]]);
        $order = $this->factory->order('+2 days', status: OrderStatus::CONFIRMED);

        static::getContainer()->get(MessageBusInterface::class)->dispatch(new SendTripReminders());

        $email = $this->emailWithSubject('напоминание');
        self::assertEmailHtmlBodyContains($email, 'Прогноз погоды');
        self::assertEmailHtmlBodyContains($email, '13…23 °C');
        self::assertEmailHtmlBodyContains($email, 'дождевик');
        self::assertNotNull($this->reload($order)->getReminderSentAt());

        $request = RecordingHttpClient::requestsTo('api.open-meteo.com')[0]['url'];
        self::assertStringContainsString('start_date='.$start->format('Y-m-d'), $request);
    }

    public function testWorkerHealth(): void
    {
        static::getContainer()->get('monitoring.cache')->clear();
        $this->client->request('GET', '/health/worker');
        self::assertResponseStatusCodeSame(503);

        static::getContainer()->get(MessageBusInterface::class)->dispatch(new RecordHeartbeat());
        $this->client->request('GET', '/health/worker');
        self::assertResponseIsSuccessful();
        self::assertSame('ok', json_decode((string) $this->client->getResponse()->getContent(), true)['status']);
    }

    /**
     * @param array<string, mixed> $update
     *
     * @return array<string, mixed>
     */
    private function telegram(array $update): array
    {
        $this->client->request('POST', '/webhook/telegram', server: [
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'test-webhook-secret',
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode(['update_id' => random_int(1, 1000000)] + $update));
        self::assertResponseIsSuccessful();

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    private function viber(array $event): array
    {
        $body = (string) json_encode($event);
        $this->client->request('POST', '/webhook/viber', server: [
            'HTTP_X_VIBER_CONTENT_SIGNATURE' => hash_hmac('sha256', $body, 'viber-test-token'),
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);
        self::assertResponseIsSuccessful();

        return (array) json_decode((string) $this->client->getResponse()->getContent() ?: '{}', true);
    }
}
