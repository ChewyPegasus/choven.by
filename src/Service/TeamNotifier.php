<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContactRequest;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Review;
use App\Entity\WaitlistEntry;
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
        $extras = [];
        foreach ($order->getExtras() as $extra) {
            $extras[] = $extra->getLocalizedName('ru').($extra->getUnit()->hasQuantity() ? ' ×'.$extra->getQuantity() : '');
        }

        $this->telegram(\sprintf(
            "🛶 <b>Новая бронь %s</b>\n%s — %s, %d чел.\n%s · %s%s%s\n%s, %s%s\n%s",
            $order->getReference(),
            $order->getStartDate()?->format('d.m.Y'),
            $this->translator->trans('tour.days', ['%count%' => (int) $order->getDurationDays()], 'messages', 'ru'),
            (int) $order->getAmountOfPeople(),
            $order->getRiver()?->trans($this->translator, 'ru'),
            $order->getPackage()?->trans($this->translator, 'ru'),
            [] !== $extras ? "\n➕ ".$this->escape(implode(', ', $extras)) : '',
            null !== $order->getEstimatedPrice()
                ? "\n💰 ".$order->getEstimatedPrice().' BYN'
                    .($order->getDiscount() > 0 ? ' (промокод '.$order->getPromoCode()?->getCode().': −'.$order->getDiscount().')' : '')
                    .($order->getGiftAmount() > 0 ? ' (сертификат −'.$order->getGiftAmount().')' : '')
                : '',
            $this->escape($order->getName()),
            $order->getPhoneFormatted() ?? '',
            null !== $order->getDescription() ? "\n💬 ".$this->escape($order->getDescription()) : '',
            $this->adminUrl('app_admin_order_edit', ['id' => $order->getId()]),
        ));
    }

    /**
     * @param bool $refundable cancelled in time: the prepayment has to be returned
     */
    public function orderCancelled(Order $order, bool $refundable = false): void
    {
        $this->mailer->sendOrderCancelledNotification($order, $refundable);
        $this->telegram(\sprintf(
            "❌ <b>Отмена брони %s</b>\n%s, %d чел. — %s%s\n%s",
            $order->getReference(),
            $order->getStartDate()?->format('d.m.Y'),
            (int) $order->getAmountOfPeople(),
            $this->escape($order->getName()),
            $order->getPaidAmount() > 0
                ? ($refundable ? "\n💸 Вернуть предоплату: ".$order->getPaidAmount().' BYN' : "\n💰 Предоплата ".$order->getPaidAmount().' BYN не возвращается (поздняя отмена)')
                : '',
            $this->adminUrl('app_admin_order_edit', ['id' => $order->getId()]),
        ));
    }

    public function orderRescheduled(Order $order, ?\DateTimeImmutable $previousStart): void
    {
        $this->telegram(\sprintf(
            "🔁 <b>Перенос брони %s</b>\n%s → %s, %d чел. — %s\n%s",
            $order->getReference(),
            $previousStart?->format('d.m.Y') ?? '?',
            $order->getStartDate()?->format('d.m.Y'),
            (int) $order->getAmountOfPeople(),
            $this->escape($order->getName()),
            $this->adminUrl('app_admin_order_edit', ['id' => $order->getId()]),
        ));
    }

    public function orderExpired(Order $order): void
    {
        $this->telegram(\sprintf(
            "⌛ <b>Бронь %s отменена автоматически</b>: не поступила предоплата\n%s, %d чел. — %s\n%s",
            $order->getReference(),
            $order->getStartDate()?->format('d.m.Y'),
            (int) $order->getAmountOfPeople(),
            $this->escape($order->getName()),
            $this->adminUrl('app_admin_order_edit', ['id' => $order->getId()]),
        ));
    }

    public function paymentReceived(Payment $payment): void
    {
        $order = $payment->getOrder();
        $gift = $payment->getGiftCertificate();

        $this->telegram(match (true) {
            null !== $order => \sprintf(
                "💳 <b>Оплата %d BYN</b> по брони %s (%s, %s)%s\n%s",
                $payment->getAmount(),
                $order->getReference(),
                $order->getStartDate()?->format('d.m.Y'),
                $this->escape($order->getName()),
                $payment->isTest() ? ' — тестовый платёж' : '',
                $this->adminUrl('app_admin_order_edit', ['id' => $order->getId()]),
            ),
            null !== $gift => \sprintf(
                "🎁 <b>Куплен сертификат на %d BYN</b>: %s (%s)%s\n%s",
                $gift->getAmount(),
                $gift->getCode(),
                $this->escape($gift->getBuyerName()),
                $payment->isTest() ? ' — тестовый платёж' : '',
                $this->adminUrl('app_admin_gift_certificates'),
            ),
            default => \sprintf('💳 Оплата %d BYN', $payment->getAmount()),
        });
    }

    public function waitlistJoined(WaitlistEntry $entry): void
    {
        $this->telegram(\sprintf(
            "⏳ <b>Лист ожидания</b>: %s, %s, %d чел. — %s %s\n%s",
            $entry->getStartDate()?->format('d.m.Y'),
            $this->translator->trans('tour.days', ['%count%' => $entry->getDurationDays()], 'messages', 'ru'),
            $entry->getPeople(),
            $this->escape($entry->getName()),
            $entry->getPhoneFormatted() ?? $entry->getEmail(),
            $this->adminUrl('app_admin_waitlist'),
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
