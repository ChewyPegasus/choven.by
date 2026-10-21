<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendTelegramMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Posts a message to the team's Telegram chat through the Bot API.
 */
#[AsMessageHandler]
final class SendTelegramMessageHandler
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default::TELEGRAM_BOT_TOKEN)%')]
        private readonly ?string $botToken,
        #[Autowire('%env(default::TELEGRAM_CHAT_ID)%')]
        private readonly ?string $chatId,
    ) {
    }

    public function __invoke(SendTelegramMessage $message): void
    {
        if (null === $this->botToken || '' === $this->botToken || null === $this->chatId || '' === $this->chatId) {
            return;
        }

        // A non-2xx answer throws, so Messenger retries the message later.
        $this->httpClient->request('POST', \sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken), [
            'json' => [
                'chat_id' => $this->chatId,
                'text' => $message->text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ],
            'timeout' => 10,
        ])->getContent();
    }
}
