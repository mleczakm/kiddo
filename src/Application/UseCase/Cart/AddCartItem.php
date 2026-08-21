<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartItemRepositoryInterface;
use App\Application\Repository\CartRepositoryInterface;
use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Service\Pricing\PriceQuoter;
use App\Domain\Commerce\Cart\CartItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Adds one ticket/participant selection to a cart, priced immediately with
 * the cart's currently-applied promotion code (if any) - see RepriceCart for
 * refreshing every item's price later. Stage 10 of the commerce rollout
 * plan.
 */
final readonly class AddCartItem
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private CartItemRepositoryInterface $cartItemRepository,
        private LessonRepositoryInterface $lessonRepository,
        private ChildRepositoryInterface $childRepository,
        private PriceQuoter $priceQuoter,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(
        Ulid $cartId,
        string $lessonId,
        string $ticketType,
        ?string $participantId,
        int $requestingUserId,
    ): CartItem {
        $cart = $this->cartRepository->find($cartId);
        if ($cart === null || $cart->customerId !== $requestingUserId) {
            throw new \InvalidArgumentException(sprintf('Cart %s not found for this customer.', $cartId));
        }
        $cart->assertOpen();

        $lesson = $this->lessonRepository->find(Ulid::fromString($lessonId));
        if ($lesson === null) {
            throw new \InvalidArgumentException(sprintf('Lesson %s not found.', $lessonId));
        }
        $ticketOption = $lesson->getMatchingTicketOption($ticketType);

        $participant = null;
        if ($participantId !== null) {
            $participant = $this->childRepository->find(Ulid::fromString($participantId));
            if ($participant === null || $participant->getOwner()->getId() !== $requestingUserId) {
                throw new \InvalidArgumentException(sprintf(
                    'Participant %s not found for this customer.',
                    $participantId,
                ));
            }
        }

        $lessonUlid = $lesson->getId();
        $participantUlid = $participant?->getId();
        foreach ($this->cartItemRepository->findByCart($cartId) as $existing) {
            if ($existing->matchesSelection($lessonUlid, $ticketType, $participantUlid)) {
                throw new DuplicateCartItemException(sprintf(
                    'Cart %s already has an item for this lesson/ticket-type/participant.',
                    $cartId,
                ));
            }
        }

        $quote = $this->priceQuoter->quote(
            $requestingUserId,
            $lesson,
            $ticketType,
            $ticketOption->price,
            promotionCode: $cart->promotionCode,
        );

        $item = new CartItem(
            id: new Ulid(),
            cartId: $cartId,
            lessonId: $lessonUlid,
            ticketType: $ticketType,
            participantId: $participantUlid,
            basePriceMinor: $quote->basePriceMinor,
            finalPriceMinor: $quote->finalPriceMinor,
            currency: $quote->currency,
            pricingQuoteHash: $quote->quoteHash,
            quotedAt: $quote->computedAt,
        );

        $this->em->persist($item);
        $cart->touch();
        $this->em->flush();

        return $item;
    }
}
