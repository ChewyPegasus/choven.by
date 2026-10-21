<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Mailer\AppMailer;
use App\Message\CompletePastOrders;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CompletePastOrdersHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $entityManager,
        private readonly AppMailer $mailer,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CompletePastOrders $message): int
    {
        $today = $this->clock->now()->setTime(0, 0);

        /** @var list<Order> $candidates */
        $candidates = $this->orders->createQueryBuilder('o')
            ->andWhere('o.status = :confirmed')
            ->andWhere('o.startDate < :today')
            ->setParameter('confirmed', OrderStatus::CONFIRMED)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        $completed = 0;
        foreach ($candidates as $order) {
            if ($order->getEndDate() >= $today) {
                continue; // still on the water
            }

            $order->setStatus(OrderStatus::COMPLETED);
            $this->entityManager->flush();
            $this->mailer->sendReviewInvitation($order);
            ++$completed;
        }

        if ($completed > 0) {
            $this->logger->info('Marked {count} past bookings as completed', ['count' => $completed]);
        }

        return $completed;
    }
}
