<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FailedEmail;
use App\Repository\Interfaces\FailedEmailRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FailedEmailRepository extends ServiceEntityRepository implements FailedEmailRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FailedEmail::class);
    }

    public function save($failedEmail): void
    {
        $this->getEntityManager()->persist($failedEmail);
        $this->getEntityManager()->flush();
    }

    public function remove($failedEmail): void
    {
        $this->getEntityManager()->remove($failedEmail);
        $this->getEntityManager()->flush();
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
