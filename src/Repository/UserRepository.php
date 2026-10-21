<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findOneByPhone(PhoneNumber $phone): ?User
    {
        return $this->findOneBy(['phone' => PhoneNumberUtil::getInstance()->format($phone, PhoneNumberFormat::E164)]);
    }

    /**
     * @return Page<User>
     */
    public function search(?string $query, ?string $role, int $page, int $perPage = 25): Page
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.createdAt', \SortDirection::Descending);

        if (null !== $query && '' !== trim($query)) {
            $digits = preg_replace('/\D+/', '', $query);
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.name) LIKE :q'.('' !== $digits ? ' OR u.phone LIKE :digits' : ''))
                ->setParameter('q', '%'.mb_strtolower(trim($query)).'%');
            if ('' !== $digits) {
                $qb->setParameter('digits', '%'.$digits.'%');
            }
        }

        if ('admin' === $role) {
            $qb->andWhere('u.id IN (:adminIds)')->setParameter('adminIds', $this->findAdminIds() ?: [0]);
        } elseif ('unverified' === $role) {
            $qb->andWhere('u.isVerified = false');
        }

        /* @var Page<User> */
        return Page::fromQueryBuilder($qb, $page, $perPage);
    }

    /**
     * Roles live in a JSON column, which DQL cannot search, so this one goes through plain SQL.
     *
     * @return list<int>
     */
    public function findAdminIds(): array
    {
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT id FROM users WHERE CAST(roles AS TEXT) LIKE :admin',
            ['admin' => '%'.User::ROLE_ADMIN.'%'],
        );

        return array_map('intval', $ids);
    }

    /**
     * @return list<User>
     */
    public function findAdmins(): array
    {
        $ids = $this->findAdminIds();

        return [] === $ids ? [] : $this->findBy(['id' => $ids], ['email' => 'ASC']);
    }

    public function countAdmins(): int
    {
        return \count($this->findAdminIds());
    }

    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->remove($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
