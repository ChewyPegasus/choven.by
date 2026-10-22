<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\Order;
use App\Entity\Payment;
use App\Messenger\ClientNotifier;
use App\Model\DayForecast;
use App\Service\WeatherService;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Everything the customer hears about a booking: emails plus Telegram / Viber messages
 * when they connected a messenger.
 */
class CustomerNotifier
{
    public function __construct(
        private readonly AppMailer $mailer,
        private readonly ClientNotifier $messengers,
        private readonly WeatherService $weather,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function orderPlaced(Order $order): void
    {
        $this->mailer->sendOrderConfirmation($order);
        $this->messengers->notify($order, 'messenger.notify.placed');
    }

    public function statusChanged(Order $order): void
    {
        $this->mailer->sendOrderStatusChanged($order);
        $this->messengers->notify($order, 'messenger.notify.status.'.$order->getStatus()->value);
    }

    public function paymentReceived(Order $order, Payment $payment): void
    {
        $this->mailer->sendPaymentReceived($order, $payment);
        $this->messengers->notify($order, 'messenger.notify.paid', ['%amount%' => (string) $payment->getAmount()]);
    }

    public function rescheduled(Order $order, ?\DateTimeImmutable $previousStart): void
    {
        $this->mailer->sendOrderRescheduled($order, $previousStart);
        $this->messengers->notify($order, 'messenger.notify.rescheduled');
    }

    public function expired(Order $order): void
    {
        $this->mailer->sendOrderExpired($order);
        $this->messengers->notify($order, 'messenger.notify.expired');
    }

    public function reviewInvitation(Order $order): void
    {
        $this->mailer->sendReviewInvitation($order);
        $this->messengers->notify($order, 'messenger.notify.review');
    }

    /**
     * "Your trip is in two days", with the weather forecast for the place of the trip.
     */
    public function tripReminder(Order $order): void
    {
        $forecast = $this->weather->forOrder($order);
        $this->mailer->sendTripReminder($order, $forecast);
        $this->messengers->notify($order, 'messenger.notify.reminder', ['%weather%' => $this->weatherSummary($forecast, $order->getLocale())]);
    }

    /**
     * @param list<DayForecast> $forecast
     */
    private function weatherSummary(array $forecast, string $locale): string
    {
        $lines = [];
        foreach ($forecast as $day) {
            $lines[] = \sprintf(
                '%s %s: %s, %d…%d °C, %s %d%%',
                $day->getEmoji(),
                $day->date->format('d.m'),
                $this->translator->trans('weather.kind.'.$day->getKind(), [], 'messages', $locale),
                $day->temperatureMin,
                $day->temperatureMax,
                $this->translator->trans('weather.rain_short', [], 'messages', $locale),
                $day->precipitationProbability,
            );
        }

        return [] === $lines ? '' : "\n\n".implode("\n", $lines);
    }
}
