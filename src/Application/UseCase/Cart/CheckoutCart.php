<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartItemRepositoryInterface;
use App\Application\Repository\CartRepositoryInterface;
use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\CustomerOrderRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\Commerce\OrderItemSelection;
use App\Application\Service\Commerce\OrderPlacementService;
use App\Application\Service\Payment\PaymentCodeGenerator;
use App\Application\Service\Pricing\PriceQuoter;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Entity\TicketOption;
use Brick\Money\Money;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Converts an open cart into a real order - bookings, one shared Payment,
 * a CustomerOrder and one OrderLine per item, via OrderPlacementService
 * (Stage 10 of the commerce rollout plan). Idempotent: calling this again on
 * an already-converted cart returns the same order rather than placing a
 * second one, since Cart::status is the single source of truth for whether
 * conversion already happened - see Cart::convert()'s optimistic-locked
 * $version field for the (accepted, narrow) concurrent-double-submit case.
 *
 * Every item is re-quoted here, ignoring whatever price CartItem last
 * cached - "cart prices are quotes" until the moment of conversion.
 */
final readonly class CheckoutCart
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private CartItemRepositoryInterface $cartItemRepository,
        private CustomerOrderRepositoryInterface $orderRepository,
        private LessonRepositoryInterface $lessonRepository,
        private ChildRepositoryInterface $childRepository,
        private UserRepositoryInterface $userRepository,
        private PriceQuoter $priceQuoter,
        private PaymentCodeGenerator $paymentCodeGenerator,
        private OrderPlacementService $orderPlacementService,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Ulid $cartId, int $requestingUserId, ?string $paymentCode = null): CustomerOrder
    {
        $checkout = fn(): CustomerOrder => $this->checkoutTransactionally($cartId, $requestingUserId, $paymentCode);

        if ($this->em->getConnection()->isTransactionActive()) {
            return $checkout();
        }

        return $this->em->wrapInTransaction($checkout);
    }

    private function checkoutTransactionally(Ulid $cartId, int $requestingUserId, ?string $paymentCode): CustomerOrder
    {
        $cart = $this->em->find(Cart::class, $cartId, LockMode::PESSIMISTIC_WRITE);
        if ($cart === null || $cart->customerId !== $requestingUserId) {
            throw new \InvalidArgumentException(sprintf('Cart %s not found for this customer.', $cartId));
        }

        if ($cart->status === Cart::STATUS_CONVERTED) {
            $order = $cart->convertedOrderId === null ? null : $this->orderRepository->find($cart->convertedOrderId);
            if ($order === null) {
                throw new \LogicException(sprintf('Cart %s is converted but its order could not be found.', $cartId));
            }

            return $order;
        }
        $cart->assertOpen();

        $cartItems = $this->cartItemRepository->findByCart($cartId);
        if ($cartItems === []) {
            throw new \LogicException(sprintf('Cannot check out cart %s: it has no items.', $cartId));
        }

        $user = $this->userRepository->find($cart->customerId);
        if ($user === null) {
            throw new \LogicException(sprintf('Customer %d for cart %s no longer exists.', $cart->customerId, $cartId));
        }

        $items = [];
        foreach ($cartItems as $cartItem) {
            $lesson = $this->lessonRepository->find($cartItem->lessonId);
            if ($lesson === null) {
                throw new \InvalidArgumentException(sprintf(
                    'Lesson %s in cart %s no longer exists.',
                    $cartItem->lessonId,
                    $cartId,
                ));
            }

            $participant = null;
            if ($cartItem->participantId !== null) {
                $participant = $this->childRepository->find($cartItem->participantId);
            }

            $baseTicketOption = $lesson->getMatchingTicketOption($cartItem->ticketType);
            $quote = $this->priceQuoter->quote(
                $cart->customerId,
                $lesson,
                $cartItem->ticketType,
                $baseTicketOption->price,
                promotionCode: $cart->promotionCode,
            );

            $items[] = new OrderItemSelection(
                $lesson,
                new TicketOption(
                    $baseTicketOption->type,
                    Money::ofMinor($quote->finalPriceMinor, $quote->currency),
                    $baseTicketOption->description,
                    $baseTicketOption->reschedulePolicy,
                ),
                $participant,
                $quote,
            );
        }

        $paymentCode ??= $this->paymentCodeGenerator->generateAvailable();

        $result = $this->orderPlacementService->place(
            user: $user,
            source: CustomerOrder::SOURCE_CART,
            paymentCode: $paymentCode,
            items: $items,
        );

        $order = $result->order ?? throw new \LogicException(
            'OrderPlacementService did not write an order for a cart checkout.',
        );
        $cart->convert($order->getId());
        $this->em->flush();

        return $order;
    }
}
