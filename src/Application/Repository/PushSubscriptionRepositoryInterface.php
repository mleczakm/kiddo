<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;

/**
 * @extends RepositoryInterface<PushSubscription>
 */
interface PushSubscriptionRepositoryInterface extends RepositoryInterface
{
    /** @return list<PushSubscription> */
    public function findByUser(User $user): array;

    public function findByEndpoint(string $endpoint): ?PushSubscription;
}
