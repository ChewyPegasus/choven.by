<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Mailer\AppMailer;
use App\Message\SendTripReminders;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendTripRemindersHandler
{
    /** Trips starting within this many days get a reminder (catches up if a run was missed). */
    private const DAYS_AHEAD = 2;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $entityManager,
        private readonly AppMailer $mailer,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(SendTripReminders $message): int
    {
        $today = $this->clock->now()->setTime(0, 0);

        /** @var list<Order> $orders */
        $orders = $this->orders->createQueryBuilder('o')
            ->andWhere('o.status = :confirmed')
            ->andWhere('o.reminderSentAt IS NULL')
            ->andWhere('o.startDate > :today')
            ->andWhere('o.startDate <= :until')
            ->setParameter('confirmed', OrderStatus::CONFIRMED)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->setParameter('until', $today->modify(\sprintf('+%d days', self::DAYS_AHEAD)), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        foreach ($orders as $order) {
            $this->mailer->sendTripReminder($order);
            $order->markReminderSent();
        }
        $this->entityManager->flush();

        return \count($orders);
    }
}
