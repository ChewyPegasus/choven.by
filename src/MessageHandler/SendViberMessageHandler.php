<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessengerChannel;
use App\Message\SendViberMessage;
use App\Messenger\ViberBot;
use App\Repository\MessengerSubscriptionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendViberMessageHandler
{
    public function __construct(
        private readonly ViberBot $bot,
        private readonly MessengerSubscriptionRepository $subscriptions,
    ) {
    }

    public function __invoke(SendViberMessage $message): void
    {
        if (!$this->bot->isEnabled()) {
            return;
        }

        $buttons = null !== $message->buttonText && null !== $message->buttonUrl
            ? [['text' => $message->buttonText, 'url' => $message->buttonUrl]]
            : [];

        // Transport errors throw (and are retried); a refusal means the user is gone.
        if (!$this->bot->sendMessage($message->receiver, $message->text, $buttons)) {
            $this->subscriptions->deactivateAll(MessengerChannel::VIBER, $message->receiver);
        }
    }
}
