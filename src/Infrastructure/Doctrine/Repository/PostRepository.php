<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\PostRepositoryInterface;
use App\Entity\Post;
use App\Entity\PostVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
final class PostRepository extends ServiceEntityRepository implements PostRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    #[\Override]
    public function findOnePublishedBySlug(string $slug, \DateTimeImmutable $now): ?Post
    {
        /** @var list<Post> $posts */
        $posts = $this
            ->createQueryBuilder('post')
            ->andWhere('post.slug = :slug')
            ->andWhere('post.status = :status')
            ->andWhere('post.publishedAt IS NOT NULL')
            ->andWhere('post.publishedAt <= :now')
            ->setParameter('slug', $slug)
            ->setParameter('status', \App\Entity\PostStatus::PUBLISHED)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $posts[0] ?? null;
    }

    /**
     * Published posts newest-first, for the public article index.
     *
     * @return list<Post>
     */
    #[\Override]
    public function findPublished(
        \DateTimeImmutable $now,
        int $limit,
        int $offset,
        bool $isAuthenticated,
        bool $isStaff,
    ): array {
        /** @var list<Post> */
        return $this
            ->visibleForViewer($this->publishedQueryBuilder($now), $isAuthenticated, $isStaff)
            ->orderBy('post.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    #[\Override]
    public function countPublished(\DateTimeImmutable $now, bool $isAuthenticated, bool $isStaff): int
    {
        return (int) $this
            ->visibleForViewer($this->publishedQueryBuilder($now)->select('COUNT(post.id)'), $isAuthenticated, $isStaff)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function publishedQueryBuilder(\DateTimeImmutable $now): QueryBuilder
    {
        return $this
            ->createQueryBuilder('post')
            ->andWhere('post.status = :status')
            ->andWhere('post.publishedAt IS NOT NULL')
            ->andWhere('post.publishedAt <= :now')
            ->setParameter('status', \App\Entity\PostStatus::PUBLISHED)
            ->setParameter('now', $now);
    }

    /**
     * Restricts a published-posts query to what a viewer in this auth state
     * may see — kept in lockstep with Post::isVisibleTo().
     */
    private function visibleForViewer(QueryBuilder $qb, bool $isAuthenticated, bool $isStaff): QueryBuilder
    {
        $visible = [PostVisibility::PUBLIC];
        if ($isAuthenticated) {
            $visible[] = PostVisibility::LOGGED_IN;
        }
        if ($isStaff) {
            $visible[] = PostVisibility::STAFF_ONLY;
        }

        return $qb->andWhere('post.visibility IN (:visible)')->setParameter('visible', $visible);
    }

    /**
     * Distinct, non-empty eyebrow values across all posts, for use as category
     * suggestions in the admin editor's datalist.
     *
     * @return list<string>
     * @throws \UnexpectedValueException
     */
    #[\Override]
    public function findDistinctEyebrows(int $limit = 50): array
    {
        $rows = $this
            ->createQueryBuilder('post')
            ->select('DISTINCT post.body.eyebrow AS eyebrow')
            ->andWhere('post.body.eyebrow IS NOT NULL')
            ->orderBy('post.body.eyebrow', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_unique(array_map('strval', $rows)));
    }
}
