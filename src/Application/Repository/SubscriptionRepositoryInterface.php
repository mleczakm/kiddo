<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Series;
use App\Entity\Subscription;
use App\Entity\User;

/**
 * @extends RepositoryInterface<Subscription>
 */
interface SubscriptionRepositoryInterface extends RepositoryInterface
{
    /** @return list<Subscription> */
    public function findActiveForUser(User $user): array;

    /** @return list<Subscription> */
    public function findAllActive(): array;

    public function findActiveFor(User $user, Series $series, ?string $childId): ?Subscription;
}
