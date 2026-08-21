<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Domain\Commerce\Cart\Cart;

/**
 * @extends RepositoryInterface<Cart>
 */
interface CartRepositoryInterface extends RepositoryInterface
{
    /**
     * "One active cart per customer/currency" (Stage 10 of the commerce
     * rollout plan) - the lookup GetOrCreateCart is built around.
     */
    public function findOpenForCustomer(int $customerId, string $currency): ?Cart;
}
