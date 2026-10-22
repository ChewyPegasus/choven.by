<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessengerChannel;
use App\Message\SendTelegramMessage;
use App\Messenger\TelegramBot;
use App\Repository\MessengerSubscriptionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

/**
 * Posts a message through the Telegram Bot API: to the team chat or to a customer's chat.
 */
#[AsMessageHandler]
final class SendTelegramMessageHandler
{
    public function __construct(
        private readonly TelegramBot $bot,
        private readonly MessengerSubscriptionRepository $subscriptions,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::TELEGRAM_CHAT_ID)%')]
        private readonly ?string $teamChatId,
    ) {
    }

    public function __invoke(SendTelegramMessage $message): void
    {
        if (!$this->bot->isEnabled()) {
            return;
        }

        $chatId = $message->chatId ?? $this->teamChatId;
        if (null === $chatId || '' === $chatId) {
            return;
        }

        try {
            // A non-2xx answer throws, so Messenger retries the message later.
            $this->bot->sendMessage($chatId, $message->text, $message->buttonText, $message->buttonUrl);
        } catch (ClientExceptionInterface $e) {
            if (null === $message->chatId || !\in_array($e->getResponse()->getStatusCode(), [400, 403], true)) {
                throw $e;
            }

            // 400 "chat not found" / 403 "bot was blocked by the user": stop writing to this customer.
            $this->subscriptions->deactivateAll(MessengerChannel::TELEGRAM, $chatId);
            $this->logger->info('Telegram chat {chat} is unreachable ({message}); subscription deactivated.', [
                'chat' => $chatId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
