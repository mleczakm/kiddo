<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\File;

/**
 * @extends RepositoryInterface<File>
 */
interface FileRepositoryInterface extends RepositoryInterface
{
    /**
     * Media-library lookup: any previously uploaded file, regardless of what
     * it's currently attached to (or not attached to at all).
     *
     * @return list<File>
     */
    public function search(string $query, int $limit = 20): array;
}
