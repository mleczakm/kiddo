<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Domain\Commerce\Pricing\PricingRule;

/**
 * @extends RepositoryInterface<PricingRule>
 */
interface PricingRuleRepositoryInterface extends RepositoryInterface
{
    /**
     * Every active rule, for PricingEngine to filter via PricingRule::appliesTo().
     * Full scope matching happens in PHP rather than SQL - with the volume of
     * rules expected at kiddo's scale, fetching all active rules and filtering
     * in memory is simpler and just as fast as duplicating appliesTo()'s logic
     * as a WHERE clause.
     *
     * @return list<PricingRule>
     */
    public function findActive(): array;

    /**
     * Ordered for the admin list view: active rules first, then by priority
     * (desc), then newest first.
     *
     * @return list<PricingRule>
     */
    public function findAllForAdmin(): array;
}
