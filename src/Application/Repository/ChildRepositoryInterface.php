<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Child;
use App\Entity\User;

/**
 * @extends RepositoryInterface<Child>
 */
interface ChildRepositoryInterface extends RepositoryInterface
{
    /** @return Child[] */
    public function findByOwner(User $user): array;
}
