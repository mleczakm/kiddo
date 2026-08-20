<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\LessonMetadata;

/**
 * @extends RepositoryInterface<LessonMetadata>
 */
interface LessonMetadataRepositoryInterface extends RepositoryInterface
{
    public function slugExists(string $slug): bool;
}
