<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Post;

/**
 * @extends RepositoryInterface<Post>
 */
interface PostRepositoryInterface extends RepositoryInterface
{
    public function findOnePublishedBySlug(string $slug, \DateTimeImmutable $now): ?Post;

    /** @return list<Post> */
    public function findPublished(
        \DateTimeImmutable $now,
        int $limit,
        int $offset,
        bool $isAuthenticated,
        bool $isStaff,
    ): array;

    public function countPublished(\DateTimeImmutable $now, bool $isAuthenticated, bool $isStaff): int;

    /** @return list<string> */
    public function findDistinctEyebrows(int $limit = 50): array;
}
