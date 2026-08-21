<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Domain\Commerce\Cart\CartItem;
use Symfony\Component\Uid\Ulid;

/**
 * @extends RepositoryInterface<CartItem>
 */
interface CartItemRepositoryInterface extends RepositoryInterface
{
    /**
     * @return list<CartItem>
     */
    public function findByCart(Ulid $cartId): array;
}
