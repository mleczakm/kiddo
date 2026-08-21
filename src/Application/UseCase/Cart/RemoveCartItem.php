<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartItemRepositoryInterface;
use App\Application\Repository\CartRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class RemoveCartItem
{
    public function __construct(
        private CartItemRepositoryInterface $cartItemRepository,
        private CartRepositoryInterface $cartRepository,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Ulid $cartItemId, int $requestingUserId): void
    {
        $item = $this->cartItemRepository->find($cartItemId);
        if ($item === null) {
            throw new \InvalidArgumentException(sprintf('Cart item %s not found.', $cartItemId));
        }

        $cart = $this->cartRepository->find($item->cartId);
        if ($cart === null || $cart->customerId !== $requestingUserId) {
            throw new \InvalidArgumentException(sprintf('Cart item %s not found for this customer.', $cartItemId));
        }
        $cart->assertOpen();

        $this->em->remove($item);
        $cart->touch();
        $this->em->flush();
    }
}
