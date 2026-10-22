<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Entity\MessengerSubscription;
use App\Enum\MessengerChannel;
use App\Message\SendViberMessage;
use App\Model\Localized;
use App\Repository\MessengerSubscriptionRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Reacts to what customers send to the Telegram and Viber bots: connecting a booking or an account
 * with a deep link, and switching notifications off.
 */
class BotUpdateHandler
{
    private const VIBER_LINK_PREFIX = 'link:';

    public function __construct(
        private readonly SubscriptionLinker $linker,
        private readonly MessengerSubscriptionRepository $subscriptions,
        private readonly ViberBot $viber,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Handles one Telegram update and returns the reply to send to that chat (or null).
     *
     * @param array<string, mixed> $update
     *
     * @return array{chat_id: string, text: string}|null
     */
    public function handleTelegram(array $update): ?array
    {
        $message = \is_array($update['message'] ?? null) ? $update['message'] : null;
        $chat = \is_array($message['chat'] ?? null) ? $message['chat'] : null;
        $text = \is_string($message['text'] ?? null) ? trim($message['text']) : '';
        // Only private chats with customers; the team group chat is ignored.
        if (null === $chat || 'private' !== ($chat['type'] ?? null) || !isset($chat['id'])) {
            return null;
        }
        $chatId = (string) $chat['id'];
        $name = \is_string($chat['first_name'] ?? null) ? $chat['first_name'] : (\is_string($chat['username'] ?? null) ? $chat['username'] : null);
        $languageCode = \is_array($message['from'] ?? null) && \is_string($message['from']['language_code'] ?? null) ? $message['from']['language_code'] : null;

        if (1 === preg_match('#^/start(?:@\w+)?(?:\s+(\S+))?$#', $text, $m)) {
            if (!isset($m[1])) {
                return ['chat_id' => $chatId, 'text' => $this->trans('messenger.bot.hello', $this->guessLocale($languageCode))];
            }

            $subscription = $this->linker->link(MessengerChannel::TELEGRAM, $chatId, $m[1], $name);

            return ['chat_id' => $chatId, 'text' => null !== $subscription
                ? $this->linkedText($subscription)
                : $this->trans('messenger.bot.invalid_link', $this->guessLocale($languageCode))];
        }

        if (1 === preg_match('#^/stop(?:@\w+)?$#', $text)) {
            $this->subscriptions->deactivateAll(MessengerChannel::TELEGRAM, $chatId);

            return ['chat_id' => $chatId, 'text' => $this->trans('messenger.bot.stopped', $this->guessLocale($languageCode))];
        }

        return ['chat_id' => $chatId, 'text' => $this->trans('messenger.bot.help', $this->guessLocale($languageCode))];
    }

    /**
     * Handles one Viber callback; returns the JSON body of the answer (a welcome message for
     * "conversation_started"), or null for an empty 200 answer.
     *
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>|null
     */
    public function handleViber(array $event): ?array
    {
        $type = $event['event'] ?? null;

        if ('conversation_started' === $type) {
            $context = \is_string($event['context'] ?? null) ? $event['context'] : '';
            $user = \is_array($event['user'] ?? null) ? $event['user'] : [];
            $locale = $this->guessLocale(\is_string($user['language'] ?? null) ? $user['language'] : null);

            // The user is not subscribed yet, so we can only answer with a welcome message:
            // the button sends the payload back as a message, which subscribes the user.
            $buttons = '' !== $context
                ? [['text' => $this->trans('messenger.bot.viber_button', $locale), 'reply' => self::VIBER_LINK_PREFIX.$context]]
                : [];

            return $this->viber->buildMessage($this->trans('messenger.bot.viber_welcome', $locale), $buttons);
        }

        if ('message' === $type) {
            $sender = \is_array($event['sender'] ?? null) ? $event['sender'] : [];
            $userId = \is_string($sender['id'] ?? null) ? $sender['id'] : null;
            $text = \is_array($event['message'] ?? null) && \is_string($event['message']['text'] ?? null) ? trim($event['message']['text']) : '';
            if (null === $userId) {
                return null;
            }
            $locale = $this->guessLocale(\is_string($sender['language'] ?? null) ? $sender['language'] : null);

            if (str_starts_with($text, self::VIBER_LINK_PREFIX)) {
                $name = \is_string($sender['name'] ?? null) ? $sender['name'] : null;
                $subscription = $this->linker->link(MessengerChannel::VIBER, $userId, substr($text, \strlen(self::VIBER_LINK_PREFIX)), $name);
                $reply = null !== $subscription ? $this->linkedText($subscription, html: false) : $this->trans('messenger.bot.invalid_link', $locale);
            } elseif (\in_array(mb_strtolower($text), ['stop', 'стоп', '/stop'], true)) {
                $this->subscriptions->deactivateAll(MessengerChannel::VIBER, $userId);
                $reply = $this->trans('messenger.bot.stopped_viber', $locale);
            } else {
                $reply = $this->trans('messenger.bot.help_viber', $locale);
            }

            $this->bus->dispatch(new SendViberMessage($userId, $reply));

            return null;
        }

        if ('unsubscribed' === $type && \is_string($event['user_id'] ?? null)) {
            $this->subscriptions->deactivateAll(MessengerChannel::VIBER, $event['user_id']);
        }

        return null;
    }

    private function linkedText(MessengerSubscription $subscription, bool $html = true): string
    {
        $order = $subscription->getOrder();
        $locale = $order?->getLocale() ?? $subscription->getUser()?->getLocale() ?? Localized::DEFAULT_LOCALE;
        $reference = null !== $order ? ($html ? '<b>'.$order->getReference().'</b>' : $order->getReference()) : '';

        return null !== $order
            ? $this->trans('messenger.bot.linked_order', $locale, ['%reference%' => $reference])
            : $this->trans('messenger.bot.linked_account', $locale);
    }

    private function guessLocale(?string $languageCode): string
    {
        $language = substr((string) $languageCode, 0, 2);

        return \in_array($language, Localized::LOCALES, true) ? $language : Localized::DEFAULT_LOCALE;
    }

    /**
     * @param array<string, string> $params
     */
    private function trans(string $key, string $locale, array $params = []): string
    {
        return $this->translator->trans($key, $params, 'messages', $locale);
    }
}
