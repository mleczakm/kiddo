<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Domain\Commerce\Order\CustomerOrder;

/**
 * @extends RepositoryInterface<CustomerOrder>
 *
 * No extra query methods yet - CheckoutCart only ever needs find() to look
 * up an already-converted cart's order for its idempotent short-circuit.
 */
interface CustomerOrderRepositoryInterface extends RepositoryInterface {}
