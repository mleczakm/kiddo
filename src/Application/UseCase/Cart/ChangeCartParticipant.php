<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartItemRepositoryInterface;
use App\Application\Repository\CartRepositoryInterface;
use App\Application\Service\Commerce\CartItemLookup;
use App\Domain\Commerce\Cart\CartItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class ChangeCartParticipant
{
    public function __construct(
        private CartItemRepositoryInterface $cartItemRepository,
        private CartRepositoryInterface $cartRepository,
        private CartItemLookup $lookup,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @throws \App\Application\UseCase\Cart\DuplicateCartItemException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     * @throws \LogicException
     */
    public function __invoke(Ulid $cartItemId, ?string $participantId, int $requestingUserId): CartItem
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

        $participant = $participantId === null
            ? null
            : $this->lookup->requireParticipant($participantId, $requestingUserId);

        $participantUlid = $participant?->getId();
        foreach ($this->cartItemRepository->findByCart($item->cartId) as $existing) {
            if (
                !$existing->id->equals($item->id)
                && $existing->matchesSelection($item->lessonId, $item->ticketType, $participantUlid)
            ) {
                throw new DuplicateCartItemException(sprintf(
                    'Cart %s already has an item for this lesson/ticket-type/participant.',
                    $item->cartId,
                ));
            }
        }

        $item->participantId = $participantUlid;
        $cart->touch();
        $this->em->flush();

        return $item;
    }
}
