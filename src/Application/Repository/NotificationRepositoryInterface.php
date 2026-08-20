<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Notification;
use App\Entity\User;

/**
 * @extends RepositoryInterface<Notification>
 */
interface NotificationRepositoryInterface extends RepositoryInterface
{
    /** @return Notification[] */
    public function findRecentForUser(User $user, int $limit = 20): array;

    public function countUnreadForUser(User $user): int;

    public function hardDeleteOlderThan(\DateTimeImmutable $cutoff): int;
}
