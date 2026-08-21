<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartItemRepositoryInterface;
use App\Application\Repository\CartRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Service\Pricing\PriceQuoter;
use App\Domain\Commerce\Cart\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Refreshes every item's quote against the cart's currently-applied
 * promotion code and today's active pricing rules - "cart prices are
 * quotes" (Stage 10 of the commerce rollout plan), so nothing here is
 * authoritative until CheckoutCart re-quotes one final time at conversion.
 * ApplyPromotionCode/RemovePromotionCode call reprice() directly on an
 * already-loaded, already-authorized cart rather than going through
 * __invoke() a second time.
 */
final readonly class RepriceCart
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private CartItemRepositoryInterface $cartItemRepository,
        private LessonRepositoryInterface $lessonRepository,
        private PriceQuoter $priceQuoter,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Ulid $cartId, int $requestingUserId): Cart
    {
        $cart = $this->cartRepository->find($cartId);
        if ($cart === null || $cart->customerId !== $requestingUserId) {
            throw new \InvalidArgumentException(sprintf('Cart %s not found for this customer.', $cartId));
        }
        $cart->assertOpen();

        $this->reprice($cart);
        $this->em->flush();

        return $cart;
    }

    public function reprice(Cart $cart): void
    {
        foreach ($this->cartItemRepository->findByCart($cart->id) as $item) {
            $lesson = $this->lessonRepository->find($item->lessonId);
            if ($lesson === null) {
                // The lesson this item pointed at is gone - leave its last known
                // quote in place rather than failing the whole reprice; RemoveCartItem
                // is the explicit way to clear a stale item.
                continue;
            }

            $ticketOption = $lesson->getMatchingTicketOption($item->ticketType);
            $quote = $this->priceQuoter->quote(
                $cart->customerId,
                $lesson,
                $item->ticketType,
                $ticketOption->price,
                promotionCode: $cart->promotionCode,
            );

            $item->basePriceMinor = $quote->basePriceMinor;
            $item->finalPriceMinor = $quote->finalPriceMinor;
            $item->pricingQuoteHash = $quote->quoteHash;
            $item->quotedAt = $quote->computedAt;
        }

        $cart->touch();
    }
}
