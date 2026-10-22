<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * save()/remove() helpers for repositories extending ServiceEntityRepository.
 */
trait SaveRemoveTrait
{
    public function save(object $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(object $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
