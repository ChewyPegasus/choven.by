<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Exception\DatesUnavailableException;
use App\Mailer\AppMailer;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Business operations on bookings; controllers stay thin and always go through here.
 */
class BookingService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly AppMailer $mailer,
        private readonly TeamNotifier $teamNotifier,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityChecker $availability,
    ) {
    }

    /**
     * Saves a booking made on the site and notifies the customer and the team.
     *
     * @throws DatesUnavailableException when the last places were taken by a concurrent booking
     */
    public function place(Order $order, ?User $user, string $locale): void
    {
        $order->setLocale($locale);
        $order->setStatus(OrderStatus::NEW);

        if (null !== $user) {
            // The email field is read-only for signed-in users; never trust the submitted value.
            $order->setUser($user)->setEmail($user->getEmail());
        }

        $order->setEstimatedPrice($order->getTour()?->estimatePrice((int) $order->getAmountOfPeople()));

        // Serialize bookings so that two customers cannot take the same last places at the same moment.
        $this->entityManager->wrapInTransaction(function () use ($order): void {
            $this->lockSchedule();

            $start = $order->getStartDate();
            if (null !== $start) {
                $problems = $this->availability->check($start, (int) $order->getDurationDays(), (int) $order->getAmountOfPeople());
                if ([] !== $problems) {
                    throw new DatesUnavailableException($problems);
                }
            }

            $this->ensureUniqueReference($order);
            $this->orderRepository->save($order);
        });

        $this->logger->info('Booking {reference} placed for {date}', [
            'reference' => $order->getReference(),
            'date' => $order->getStartDate()?->format('Y-m-d'),
        ]);

        $this->mailer->sendOrderConfirmation($order);
        $this->teamNotifier->newOrder($order);
    }

    /**
     * Saves a booking created by an administrator (e.g. taken over the phone).
     */
    public function createByAdmin(Order $order, bool $notifyCustomer): void
    {
        if (null === $order->getEstimatedPrice()) {
            $order->setEstimatedPrice($order->getTour()?->estimatePrice((int) $order->getAmountOfPeople()));
        }

        $this->ensureUniqueReference($order);
        $this->orderRepository->save($order);

        if ($notifyCustomer) {
            $this->mailer->sendOrderConfirmation($order);
        }
    }

    public function cancelByCustomer(Order $order): void
    {
        if (!$order->canBeCancelledByCustomer()) {
            throw new \LogicException(\sprintf('Order %s cannot be cancelled.', $order->getReference()));
        }

        $order->setStatus(OrderStatus::CANCELLED);
        $this->orderRepository->save($order);

        $this->mailer->sendOrderStatusChanged($order);
        $this->teamNotifier->orderCancelled($order);
    }

    /**
     * Applies an admin edit; when the status changed the customer can be informed by email.
     */
    public function update(Order $order, OrderStatus $previousStatus, bool $notifyCustomer): void
    {
        $this->orderRepository->save($order);

        if (!$notifyCustomer || $previousStatus === $order->getStatus()) {
            return;
        }

        if (OrderStatus::COMPLETED === $order->getStatus()) {
            $this->mailer->sendReviewInvitation($order);
        } else {
            $this->mailer->sendOrderStatusChanged($order);
        }
    }

    /**
     * Transaction-scoped lock of the booking schedule (PostgreSQL advisory lock, released on commit).
     */
    private function lockSchedule(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $connection->executeStatement('SELECT pg_advisory_xact_lock(724205)');
        }
    }

    private function ensureUniqueReference(Order $order): void
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $existing = $this->orderRepository->findOneByReference($order->getReference());
            if (null === $existing || $existing === $order) {
                return;
            }
            $order->regenerateReference();
        }

        throw new \RuntimeException('Could not generate a unique booking reference.');
    }
}
