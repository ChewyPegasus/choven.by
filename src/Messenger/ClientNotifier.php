<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Entity\Order;
use App\Enum\MessengerChannel;
use App\Message\SendTelegramMessage;
use App\Message\SendViberMessage;
use App\Repository\MessengerSubscriptionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Booking updates for customers who connected Telegram or Viber (in addition to the emails).
 */
class ClientNotifier
{
    public function __construct(
        private readonly MessengerSubscriptionRepository $subscriptions,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TelegramBot $telegram,
        private readonly ViberBot $viber,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string                $key    translation key of the text (domain "messages")
     * @param array<string, string> $params translation parameters; %reference% and %date% are added
     */
    public function notify(Order $order, string $key, array $params = []): void
    {
        if (!$this->telegram->isEnabled() && !$this->viber->isEnabled()) {
            return;
        }

        try {
            $subscriptions = $this->subscriptions->findForOrder($order);
        } catch (\Throwable $e) {
            $this->logger->warning('Could not load messenger subscriptions: {message}', ['message' => $e->getMessage()]);

            return;
        }
        if ([] === $subscriptions) {
            return;
        }

        $locale = $order->getLocale();
        $params += [
            '%reference%' => $order->getReference(),
            '%date%' => $order->getStartDate()?->format('d.m.Y') ?? '',
        ];
        $url = $this->urlGenerator->generate('app_order_show', ['_locale' => $locale, 'token' => $order->getAccessToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $button = $this->translator->trans('messenger.notify.button', [], 'messages', $locale);

        foreach ($subscriptions as $subscription) {
            try {
                if (MessengerChannel::TELEGRAM === $subscription->getChannel() && $this->telegram->isEnabled()) {
                    $text = $this->translator->trans($key, $this->escape($params), 'messages', $locale);
                    $this->bus->dispatch(new SendTelegramMessage($text, $subscription->getExternalId(), $button, $url));
                } elseif (MessengerChannel::VIBER === $subscription->getChannel() && $this->viber->isEnabled()) {
                    $text = strip_tags($this->translator->trans($key, $params, 'messages', $locale));
                    $this->bus->dispatch(new SendViberMessage($subscription->getExternalId(), $text, $button, $url));
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Could not queue a messenger notification: {message}', ['message' => $e->getMessage()]);
            }
        }
    }

    /**
     * Telegram messages are sent as HTML: escape user-provided values.
     *
     * @param array<string, string> $params
     *
     * @return array<string, string>
     */
    private function escape(array $params): array
    {
        return array_map(static fn (string $value): string => htmlspecialchars($value, \ENT_NOQUOTES), $params);
    }
}
