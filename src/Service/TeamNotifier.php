<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContactRequest;
use App\Entity\Order;
use App\Entity\Review;
use App\Mailer\AppMailer;
use App\Message\SendTelegramMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells the team about things that need attention: by email and, when configured, in Telegram.
 */
class TeamNotifier
{
    public function __construct(
        private readonly AppMailer $mailer,
        private readonly MessageBusInterface $bus,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::TELEGRAM_BOT_TOKEN)%')]
        private readonly ?string $telegramToken = null,
    ) {
    }

    public function newOrder(Order $order): void
    {
        $this->mailer->sendNewOrderNotification($order);
        $this->telegram(\sprintf(
            "🛶 <b>Новая бронь %s</b>\n%s — %s, %d чел.\n%s · %s\n%s, %s%s\n%s",
            $order->getReference(),
            $order->getStartDate()?->format('d.m.Y'),
            $this->translator->trans('tour.days', ['%count%' => (int) $order->getDurationDays()], 'messages', 'ru'),
            (int) $order->getAmountOfPeople(),
            $order->getRiver()?->trans($this->translator, 'ru'),
            $order->getPackage()?->trans($this->translator, 'ru'),
            $this->escape($order->getName()),
            $order->getPhoneFormatted() ?? '',
            null !== $order->getDescription() ? "\n💬 ".$this->escape($order->getDescription()) : '',
            $this->adminUrl('app_admin_order_edit', ['id' => $order->getId()]),
        ));
    }

    public function orderCancelled(Order $order): void
    {
        $this->mailer->sendOrderCancelledNotification($order);
        $this->telegram(\sprintf(
            "❌ <b>Отмена брони %s</b>\n%s, %d чел. — %s\n%s",
            $order->getReference(),
            $order->getStartDate()?->format('d.m.Y'),
            (int) $order->getAmountOfPeople(),
            $this->escape($order->getName()),
            $this->adminUrl('app_admin_order_edit', ['id' => $order->getId()]),
        ));
    }

    public function contactRequest(ContactRequest $request): void
    {
        $this->mailer->sendContactRequestNotification($request);
        $this->telegram(\sprintf(
            "📞 <b>Перезвонить</b>: %s, %s%s\n%s",
            $this->escape($request->getName()),
            $request->getPhoneFormatted() ?? '',
            null !== $request->getMessage() ? "\n💬 ".$this->escape($request->getMessage()) : '',
            $this->adminUrl('app_admin_contact_requests'),
        ));
    }

    public function newReview(Review $review): void
    {
        $this->mailer->sendNewReviewNotification($review);
        $this->telegram(\sprintf(
            "⭐ <b>Отзыв %d/5</b> от %s ждёт модерации\n%s",
            (int) $review->getRating(),
            $this->escape($review->getAuthorName()),
            $this->adminUrl('app_admin_reviews'),
        ));
    }

    private function telegram(string $text): void
    {
        if (null === $this->telegramToken || '' === $this->telegramToken) {
            return;
        }

        try {
            $this->bus->dispatch(new SendTelegramMessage(mb_substr($text, 0, 4000)));
        } catch (\Throwable $e) {
            $this->logger->warning('Could not queue Telegram notification: {message}', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function adminUrl(string $route, array $params = []): string
    {
        return $this->urlGenerator->generate($route, $params + ['_locale' => 'ru'], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_NOQUOTES);
    }
}
