<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A Telegram message delivered by the worker with retries: to the team chat (no chat id)
 * or to a customer who connected the bot.
 */
final class SendTelegramMessage
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $chatId = null,
        public readonly ?string $buttonText = null,
        public readonly ?string $buttonUrl = null,
    ) {
    }
}
