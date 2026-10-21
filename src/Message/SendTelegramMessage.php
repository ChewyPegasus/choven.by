<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A notification for the team chat; delivered by the worker with retries.
 */
final class SendTelegramMessage
{
    public function __construct(public readonly string $text)
    {
    }
}
