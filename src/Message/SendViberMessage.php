<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A Viber message to a customer who subscribed to the bot.
 */
final class SendViberMessage
{
    public function __construct(
        public readonly string $receiver,
        public readonly string $text,
        public readonly ?string $buttonText = null,
        public readonly ?string $buttonUrl = null,
    ) {
    }
}
