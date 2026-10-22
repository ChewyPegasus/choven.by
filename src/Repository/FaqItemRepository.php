<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FaqItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaqItem>
 */
class FaqItemRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaqItem::class);
    }

    /**
     * @return list<FaqItem>
     */
    public function findActive(bool $homeOnly = false): array
    {
        $criteria = ['isActive' => true];
        if ($homeOnly) {
            $criteria['showOnHome'] = true;
        }

        return $this->findBy($criteria, ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return list<FaqItem>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function getNextPosition(): int
    {
        return 1 + (int) $this->createQueryBuilder('f')
            ->select('MAX(f.position)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
