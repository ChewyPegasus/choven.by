<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Entity\MessengerSubscription;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\MessengerChannel;
use App\Repository\MessengerSubscriptionRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Signed deep-link payloads ("o123x…" for a booking, "u45x…" for an account) and subscribing chats with them.
 *
 * The payload is short enough for Telegram's /start parameter (64 characters of [A-Za-z0-9_-])
 * and does not reveal the secret booking link.
 */
class SubscriptionLinker
{
    public function __construct(
        private readonly MessengerSubscriptionRepository $subscriptions,
        private readonly OrderRepository $orders,
        private readonly UserRepository $users,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function payloadForOrder(Order $order): string
    {
        return $this->sign('o'.$order->getId());
    }

    public function payloadForUser(User $user): string
    {
        return $this->sign('u'.$user->getId());
    }

    /**
     * Subscribes the chat to what the payload points to. Null when the payload is invalid.
     */
    public function link(MessengerChannel $channel, string $externalId, string $payload, ?string $displayName): ?MessengerSubscription
    {
        if (1 !== preg_match('/^([ou])(\d{1,10})x([a-f0-9]{16})$/', $payload, $m) || !hash_equals($this->sign($m[1].$m[2]), $payload)) {
            return null;
        }

        $order = 'o' === $m[1] ? $this->orders->find((int) $m[2]) : null;
        $user = 'u' === $m[1] ? $this->users->find((int) $m[2]) : null;
        if (null === $order && null === $user) {
            return null;
        }

        $subscription = $this->subscriptions->findOneFor($channel, $externalId, $order, $user)
            ?? (new MessengerSubscription($channel, $externalId, $displayName))->setOrder($order)->setUser($user);
        $subscription->setIsActive(true);
        $this->subscriptions->save($subscription);

        return $subscription;
    }

    private function sign(string $subject): string
    {
        return $subject.'x'.substr(hash_hmac('sha256', 'messenger-link|'.$subject, $this->secret), 0, 16);
    }
}
