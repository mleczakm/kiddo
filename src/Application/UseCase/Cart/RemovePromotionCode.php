<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartRepositoryInterface;
use App\Domain\Commerce\Cart\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class RemovePromotionCode
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private RepriceCart $repriceCart,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Ulid $cartId, int $requestingUserId): Cart
    {
        $cart = $this->cartRepository->find($cartId);
        if ($cart === null || $cart->customerId !== $requestingUserId) {
            throw new \InvalidArgumentException(sprintf('Cart %s not found for this customer.', $cartId));
        }
        $cart->assertOpen();

        $cart->promotionCode = null;
        $this->repriceCart->reprice($cart);
        $this->em->flush();

        return $cart;
    }
}
