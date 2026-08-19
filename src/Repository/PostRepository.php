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
}
