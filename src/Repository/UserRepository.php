<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\Interfaces\UserRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return User[]
     */
    public function findByRole(Role $role): array
    {
        $allUsers = $this->findBy([], ['email' => 'ASC']);
        
        return array_filter($allUsers, function(User $user) use ($role) {
            if ($role === Role::ADMIN) {
                return $user->isAdmin();
            }
            
            return $user->hasRole($role) || in_array('ROLE_' . strtoupper($role->value), $user->getRoles(), true);
        });
    }

    public function countByRole(Role $role): int
    {
        return count($this->findByRole($role));
    }

    /**
     * @return User[]
     */
    public function findUsersWithoutRole(Role $role): array
    {
        $allUsers = $this->findBy([], ['email' => 'ASC']);
        
        return array_filter($allUsers, function(User $user) use ($role) {
            return !$user->hasRole($role);
        });
    }

    /**
     * @return User[]
     */
    public function searchUsers(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :query OR u.phone LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save($user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function remove($user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}