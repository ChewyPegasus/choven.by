<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }

    public function findByRole(string $role): array
    {
        // no other way work properly
        $allUsers = $this->findBy([], ['email' => 'ASC']);
        
        return array_filter($allUsers, function(User $user) use ($role) {
            return in_array($role, $user->getRoles(), true);
        });
    }

    public function countByRole(string $role): int
    {
        return count($this->findByRole($role));
    }

    public function findUsersWithoutRole(string $role): array
    {
        // no other way work properly
        $allUsers = $this->findBy([], ['email' => 'ASC']);
        
        return array_filter($allUsers, function(User $user) use ($role) {
            return !in_array($role, $user->getRoles(), true);
        });
    }

    public function searchUsers(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
