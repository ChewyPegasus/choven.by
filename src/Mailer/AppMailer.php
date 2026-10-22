<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\ContactRequest;
use App\Entity\GiftCertificate;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Review;
use App\Entity\User;
use App\Entity\WaitlistEntry;
use App\Model\DayForecast;
use App\Model\Localized;
use App\Service\GiftCertificatePdf;
use App\Service\IcsGenerator;
use App\Service\SiteSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use Twig\Environment;

/**
 * All transactional emails of the site.
 *
 * Emails are rendered right away (in the recipient's language) and handed to the mailer,
 * which queues them through Messenger, so a slow or failing SMTP server never breaks a page.
 */
class AppMailer
{
    private const ADMIN_LOCALE = Localized::DEFAULT_LOCALE;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteSettings $settings,
        private readonly IcsGenerator $icsGenerator,
        private readonly GiftCertificatePdf $giftPdf,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $url = $this->orderUrl($order);
        $ics = $this->icsGenerator->forOrder($order, $url);

        $this->send(
            to: new Address($order->getEmail(), $order->getName()),
            subjectKey: 'email.order_confirmation.subject',
            subjectParams: ['%reference%' => $order->getReference()],
            template: 'emails/order_confirmation.html.twig',
            context: ['order' => $order, 'order_url' => $url],
            locale: $order->getLocale(),
            configure: static fn (Email $email) => $email->attach($ics, $order->getReference().'.ics', 'text/calendar; charset=utf-8; method=PUBLISH'),
        );
    }

    public function sendOrderStatusChanged(Order $order): void
    {
        $this->send(
            to: new Address($order->getEmail(), $order->getName()),
            subjectKey: 'email.order_status.subject',
            subjectParams: ['%reference%' => $order->getReference()],
            template: 'emails/order_status.html.twig',
            context: ['order' => $order, 'order_url' => $this->orderUrl($order)],
            locale: $order->getLocale(),
        );
    }

    /**
     * Sent after a trip is marked as completed: thanks the customer and asks for a review.
     */
    public function sendReviewInvitation(Order $order): void
    {
        $this->send(
            to: new Address($order->getEmail(), $order->getName()),
            subjectKey: 'email.review_invitation.subject',
            subjectParams: [],
            template: 'emails/review_invitation.html.twig',
            context: [
                'order' => $order,
                'review_url' => $this->urlGenerator->generate('app_review_new', [
                    '_locale' => $order->getLocale(),
                    'token' => $order->getAccessToken(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            locale: $order->getLocale(),
        );
    }

    /**
     * "Your trip is in two days": date, meeting details, weather forecast, what to bring.
     *
     * @param list<DayForecast> $forecast
     */
    public function sendTripReminder(Order $order, array $forecast = []): void
    {
        $this->send(
            to: new Address($order->getEmail(), $order->getName()),
            subjectKey: 'email.trip_reminder.subject',
            subjectParams: ['%date%' => $order->getStartDate()?->format('d.m') ?? ''],
            template: 'emails/trip_reminder.html.twig',
            context: ['order' => $order, 'order_url' => $this->orderUrl($order), 'forecast' => $forecast],
            locale: $order->getLocale(),
        );
    }

    public function sendPaymentReceived(Order $order, Payment $payment): void
    {
        $this->send(
            to: new Address($order->getEmail(), $order->getName()),
            subjectKey: 'email.payment_received.subject',
            subjectParams: ['%reference%' => $order->getReference()],
            template: 'emails/payment_received.html.twig',
            context: ['order' => $order, 'payment' => $payment, 'order_url' => $this->orderUrl($order)],
            locale: $order->getLocale(),
        );
    }

    public function sendOrderRescheduled(Order $order, ?\DateTimeImmutable $previousStart): void
    {
        $url = $this->orderUrl($order);
        $ics = $this->icsGenerator->forOrder($order, $url);

        $this->send(
            to: new Address($order->getEmail(), $order->getName()),
            subjectKey: 'email.order_rescheduled.subject',
            subjectParams: ['%reference%' => $order->getReference(), '%date%' => $order->getStartDate()?->format('d.m.Y') ?? ''],
            template: 'emails/order_rescheduled.html.twig',
            context: ['order' => $order, 'previous_start' => $previousStart, 'order_url' => $url],
            locale: $order->getLocale(),
            configure: static fn (Email $email) => $email->attach($ics, $order->getReference().'.ics', 'text/calendar; charset=utf-8; method=PUBLISH'),
        );
    }

    /**
     * The required prepayment did not arrive in time and the booking was cancelled.
     */
    public function sendOrderExpired(Order $order): void
    {
        $this->send(
            to: new Address($order->getEmail(), $order->getName()),
            subjectKey: 'email.order_expired.subject',
            subjectParams: ['%reference%' => $order->getReference()],
            template: 'emails/order_expired.html.twig',
            context: [
                'order' => $order,
                'rebook_url' => $this->urlGenerator->generate('app_order_new', ['_locale' => $order->getLocale()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            locale: $order->getLocale(),
        );
    }

    public function sendWaitlistJoined(WaitlistEntry $entry): void
    {
        $this->send(
            to: new Address($entry->getEmail(), $entry->getName()),
            subjectKey: 'email.waitlist_joined.subject',
            subjectParams: ['%date%' => $entry->getStartDate()?->format('d.m.Y') ?? ''],
            template: 'emails/waitlist_joined.html.twig',
            context: ['entry' => $entry],
            locale: $entry->getLocale(),
        );
    }

    /**
     * Places became free on the dates someone was waiting for: link to the pre-filled booking form.
     */
    public function sendWaitlistAvailable(WaitlistEntry $entry): void
    {
        $params = array_filter([
            '_locale' => $entry->getLocale(),
            'tour' => $entry->getTour()?->getSlug(),
            'date' => $entry->getStartDate()?->format('Y-m-d'),
            'duration' => $entry->getDurationDays(),
            'people' => $entry->getPeople(),
        ], static fn (mixed $v): bool => null !== $v);

        $this->send(
            to: new Address($entry->getEmail(), $entry->getName()),
            subjectKey: 'email.waitlist_available.subject',
            subjectParams: ['%date%' => $entry->getStartDate()?->format('d.m.Y') ?? ''],
            template: 'emails/waitlist_available.html.twig',
            context: ['entry' => $entry, 'booking_url' => $this->urlGenerator->generate('app_order_new', $params, UrlGeneratorInterface::ABSOLUTE_URL)],
            locale: $entry->getLocale(),
        );
    }

    /**
     * The paid certificate with the printable PDF attached.
     */
    public function sendGiftCertificate(GiftCertificate $gift): void
    {
        $url = $this->urlGenerator->generate('app_gift_show', ['_locale' => $gift->getLocale(), 'token' => $gift->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $pdf = $this->giftPdf->render($gift);

        $this->send(
            to: new Address($gift->getBuyerEmail(), $gift->getBuyerName()),
            subjectKey: 'email.gift_certificate.subject',
            subjectParams: ['%amount%' => (string) $gift->getAmount()],
            template: 'emails/gift_certificate.html.twig',
            context: ['gift' => $gift, 'gift_url' => $url],
            locale: $gift->getLocale(),
            configure: static fn (Email $email) => null !== $pdf ? $email->attach($pdf, $gift->getCode().'.pdf', 'application/pdf') : $email,
        );
    }

    public function sendNewOrderNotification(Order $order): void
    {
        $this->send(
            to: new Address($this->settings->getNotificationEmail()),
            subjectKey: 'email.admin_new_order.subject',
            subjectParams: ['%reference%' => $order->getReference(), '%date%' => $order->getStartDate()?->format('d.m.Y') ?? ''],
            template: 'emails/admin/new_order.html.twig',
            context: ['order' => $order, 'admin_url' => $this->adminOrderUrl($order)],
            locale: self::ADMIN_LOCALE,
            configure: static fn (Email $email) => $email->replyTo(new Address($order->getEmail(), $order->getName())),
        );
    }

    /**
     * @param bool $refundable the customer cancelled in time and the prepayment must be returned
     */
    public function sendOrderCancelledNotification(Order $order, bool $refundable = false): void
    {
        $this->send(
            to: new Address($this->settings->getNotificationEmail()),
            subjectKey: 'email.admin_order_cancelled.subject',
            subjectParams: ['%reference%' => $order->getReference()],
            template: 'emails/admin/order_cancelled.html.twig',
            context: ['order' => $order, 'admin_url' => $this->adminOrderUrl($order), 'refundable' => $refundable],
            locale: self::ADMIN_LOCALE,
        );
    }

    public function sendContactRequestNotification(ContactRequest $request): void
    {
        $this->send(
            to: new Address($this->settings->getNotificationEmail()),
            subjectKey: 'email.admin_contact_request.subject',
            subjectParams: ['%name%' => $request->getName()],
            template: 'emails/admin/contact_request.html.twig',
            context: [
                'request' => $request,
                'admin_url' => $this->urlGenerator->generate('app_admin_contact_requests', ['_locale' => self::ADMIN_LOCALE], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            locale: self::ADMIN_LOCALE,
            configure: static function (Email $email) use ($request): void {
                if (null !== $request->getEmail()) {
                    $email->replyTo(new Address($request->getEmail(), $request->getName()));
                }
            },
        );
    }

    public function sendNewReviewNotification(Review $review): void
    {
        $this->send(
            to: new Address($this->settings->getNotificationEmail()),
            subjectKey: 'email.admin_new_review.subject',
            subjectParams: ['%rating%' => (string) $review->getRating()],
            template: 'emails/admin/new_review.html.twig',
            context: [
                'review' => $review,
                'admin_url' => $this->urlGenerator->generate('app_admin_reviews', ['_locale' => self::ADMIN_LOCALE], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            locale: self::ADMIN_LOCALE,
        );
    }

    public function sendEmailVerification(User $user, string $signedUrl, int $expiresInHours): void
    {
        $this->send(
            to: new Address($user->getEmail(), (string) $user->getName()),
            subjectKey: 'email.verification.subject',
            subjectParams: [],
            template: 'emails/verify_email.html.twig',
            context: ['user' => $user, 'signed_url' => $signedUrl, 'expires_in_hours' => $expiresInHours],
            locale: $user->getLocale(),
        );
    }

    public function sendPasswordReset(User $user, ResetPasswordToken $token): void
    {
        $this->send(
            to: new Address($user->getEmail(), (string) $user->getName()),
            subjectKey: 'email.password_reset.subject',
            subjectParams: [],
            template: 'emails/reset_password.html.twig',
            context: [
                'user' => $user,
                'reset_url' => $this->urlGenerator->generate('app_reset_password', [
                    '_locale' => $user->getLocale(),
                    'token' => $token->getToken(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'expires_at' => $token->getExpiresAt(),
            ],
            locale: $user->getLocale(),
        );
    }

    /**
     * @param array<string, string>         $subjectParams
     * @param array<string, mixed>          $context
     * @param (callable(Email): mixed)|null $configure
     */
    private function send(
        Address $to,
        string $subjectKey,
        array $subjectParams,
        string $template,
        array $context,
        string $locale,
        ?callable $configure = null,
    ): void {
        try {
            $email = $this->localeSwitcher->runWithLocale($locale, function () use ($to, $subjectKey, $subjectParams, $template, $context, $locale): Email {
                $subject = $this->translator->trans($subjectKey, $subjectParams, 'messages', $locale);
                $html = $this->twig->render($template, $context + ['subject' => $subject, 'email_locale' => $locale]);

                return (new Email())
                    ->from(Address::create($this->from))
                    ->to($to)
                    ->subject($subject)
                    ->html($html)
                    ->text(HtmlToText::convert($html));
            });

            if (null !== $configure) {
                $configure($email);
            }

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // The page that triggered the email must keep working; the error ends up in the logs.
            $this->logger->error('Could not queue email "{template}" to {to}: {message}', [
                'template' => $template,
                'to' => $to->getAddress(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function orderUrl(Order $order): string
    {
        return $this->urlGenerator->generate('app_order_show', [
            '_locale' => $order->getLocale(),
            'token' => $order->getAccessToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function adminOrderUrl(Order $order): string
    {
        return $this->urlGenerator->generate('app_admin_order_edit', [
            '_locale' => self::ADMIN_LOCALE,
            'id' => $order->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
