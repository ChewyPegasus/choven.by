<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MessengerSubscription;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\MessengerChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessengerSubscription>
 */
class MessengerSubscriptionRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessengerSubscription::class);
    }

    /**
     * Active subscriptions that should hear about this booking: linked to the booking itself
     * or to the account it belongs to. One per chat, even if linked both ways.
     *
     * @return list<MessengerSubscription>
     */
    public function findForOrder(Order $order): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.isActive = true')
            ->andWhere('s.order = :order'.(null !== $order->getUser() ? ' OR s.user = :user' : ''))
            ->setParameter('order', $order)
            ->orderBy('s.id', \SortDirection::Ascending);
        if (null !== $order->getUser()) {
            $qb->setParameter('user', $order->getUser());
        }

        /** @var list<MessengerSubscription> $all */
        $all = $qb->getQuery()->getResult();

        $unique = [];
        foreach ($all as $subscription) {
            $unique[$subscription->getChannel()->value.':'.$subscription->getExternalId()] ??= $subscription;
        }

        return array_values($unique);
    }

    /**
     * @return list<MessengerSubscription>
     */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user, 'isActive' => true], ['id' => 'ASC']);
    }

    public function findOneFor(MessengerChannel $channel, string $externalId, ?Order $order, ?User $user): ?MessengerSubscription
    {
        return $this->findOneBy(['channel' => $channel, 'externalId' => $externalId, 'order' => $order, 'user' => $user]);
    }

    public function deactivateAll(MessengerChannel $channel, string $externalId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->update()
            ->set('s.isActive', 'false')
            ->andWhere('s.channel = :channel')
            ->andWhere('s.externalId = :id')
            ->setParameter('channel', $channel)
            ->setParameter('id', $externalId)
            ->getQuery()
            ->execute();
    }
}
