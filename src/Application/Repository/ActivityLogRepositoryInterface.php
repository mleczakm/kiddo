<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;

/**
 * @extends RepositoryInterface<ActivityLog>
 */
interface ActivityLogRepositoryInterface extends RepositoryInterface
{
    /** @return ActivityLog[] */
    public function findRecent(int $limit = 12): array;

    /** @return ActivityLog[] */
    public function findBySubject(User $subject, int $limit = 20): array;

    /** @return ActivityLog[] */
    public function findByBookingId(string $bookingId, int $limit = 20): array;

    /** @return ActivityLog[] */
    public function findByPricingRuleId(string $pricingRuleId, int $limit = 20): array;

    public function existsByDedupeKey(string $dedupeKey): bool;
}
