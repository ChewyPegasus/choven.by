<?php

declare(strict_types=1);

namespace App\Model;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * One page of a Doctrine query result.
 *
 * @template T
 */
final class Page
{
    /**
     * @param list<T> $items
     */
    private function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * @return self<mixed>
     */
    public static function fromQueryBuilder(QueryBuilder $qb, int $page, int $perPage = 20, bool $fetchJoin = false): self
    {
        $page = max(1, $page);
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        $paginator = new Paginator($qb, $fetchJoin);
        $total = \count($paginator);

        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            return self::fromQueryBuilder($qb, $lastPage, $perPage, $fetchJoin);
        }

        /** @var list<mixed> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return new self($items, $total, $page, $perPage);
    }

    public function getPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->getPages();
    }

    public function getFirstItemNumber(): int
    {
        return 0 === $this->total ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function getLastItemNumber(): int
    {
        return min($this->page * $this->perPage, $this->total);
    }

    /**
     * Page numbers to render, with null for "…" gaps: [1, null, 4, 5, 6, null, 12].
     *
     * @return list<int|null>
     */
    public function getPageRange(int $around = 2): array
    {
        $pages = $this->getPages();
        $range = [];
        $previous = 0;
        for ($i = 1; $i <= $pages; ++$i) {
            if (1 === $i || $pages === $i || abs($i - $this->page) <= $around) {
                if ($i - $previous > 1) {
                    $range[] = null;
                }
                $range[] = $i;
                $previous = $i;
            }
        }

        return $range;
    }
}
