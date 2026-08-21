<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\CartRepositoryInterface;
use App\Domain\Commerce\Cart\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository implements CartRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    #[\Override]
    public function findOpenForCustomer(int $customerId, string $currency): ?Cart
    {
        return $this->findOneBy([
            'customerId' => $customerId,
            'currency' => $currency,
            'status' => Cart::STATUS_OPEN,
        ]);
    }
}
