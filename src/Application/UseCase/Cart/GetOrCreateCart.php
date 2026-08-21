<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartRepositoryInterface;
use App\Domain\Commerce\Cart\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Entry point every other cart use case sits behind: returns the customer's
 * one open cart for $currency, creating it if none exists yet (Stage 10 of
 * the commerce rollout plan - "one active cart per customer/currency").
 */
final readonly class GetOrCreateCart
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(int $customerId, string $currency): Cart
    {
        $cart = $this->cartRepository->findOpenForCustomer($customerId, $currency);
        if ($cart !== null) {
            return $cart;
        }

        $cart = new Cart(id: new Ulid(), customerId: $customerId, currency: $currency);
        $this->em->persist($cart);
        $this->em->flush();

        return $cart;
    }
}
