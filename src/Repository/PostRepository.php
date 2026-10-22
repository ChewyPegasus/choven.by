<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use App\Model\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * @return Page<Post>
     */
    public function findPublishedPage(int $page, int $perPage = 9): Page
    {
        /* @var Page<Post> */
        return Page::fromQueryBuilder($this->published(), $page, $perPage);
    }

    /**
     * @return list<Post>
     */
    public function findLatest(int $limit, ?Post $except = null): array
    {
        $qb = $this->published()->setMaxResults($limit);
        if (null !== $except) {
            $qb->andWhere('p != :except')->setParameter('except', $except);
        }

        /* @var list<Post> */
        return $qb->getQuery()->getResult();
    }

    public function findOnePublishedBySlug(string $slug): ?Post
    {
        /* @var Post|null */
        return $this->published()
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Page<Post>
     */
    public function findAdminPage(int $page): Page
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.isPublished', \SortDirection::Ascending)
            ->addOrderBy('p.publishedAt', \SortDirection::Descending)
            ->addOrderBy('p.createdAt', \SortDirection::Descending);

        /* @var Page<Post> */
        return Page::fromQueryBuilder($qb, $page);
    }

    private function published(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', \SortDirection::Descending)
            ->addOrderBy('p.id', \SortDirection::Descending);
    }
}
