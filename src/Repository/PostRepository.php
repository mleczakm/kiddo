<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
final class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

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
    public function findPublished(\DateTimeImmutable $now, int $limit, int $offset = 0): array
    {
        /** @var list<Post> */
        return $this
            ->createQueryBuilder('post')
            ->andWhere('post.status = :status')
            ->andWhere('post.publishedAt IS NOT NULL')
            ->andWhere('post.publishedAt <= :now')
            ->setParameter('status', \App\Entity\PostStatus::PUBLISHED)
            ->setParameter('now', $now)
            ->orderBy('post.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countPublished(\DateTimeImmutable $now): int
    {
        return (int) $this
            ->createQueryBuilder('post')
            ->select('COUNT(post.id)')
            ->andWhere('post.status = :status')
            ->andWhere('post.publishedAt IS NOT NULL')
            ->andWhere('post.publishedAt <= :now')
            ->setParameter('status', \App\Entity\PostStatus::PUBLISHED)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Distinct, non-empty eyebrow values across all posts, for use as category
     * suggestions in the admin editor's datalist.
     *
     * @return list<string>
     * @throws \UnexpectedValueException
     */
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
