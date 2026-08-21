<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartItemRepositoryInterface;
use App\Application\Repository\CartRepositoryInterface;
use App\Application\Repository\ChildRepositoryInterface;
use App\Domain\Commerce\Cart\CartItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class ChangeCartParticipant
{
    public function __construct(
        private CartItemRepositoryInterface $cartItemRepository,
        private CartRepositoryInterface $cartRepository,
        private ChildRepositoryInterface $childRepository,
        private EntityManagerInterface $em,
    ) {}

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
