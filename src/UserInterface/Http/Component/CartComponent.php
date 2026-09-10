<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Consent\WorkshopTermsCollector;
use App\Application\Consent\WorkshopTermsEvidence;
use App\Application\Repository\CartItemRepositoryInterface;
use App\Application\Repository\CartRepositoryInterface;
use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\UseCase\Cart\ApplyPromotionCode;
use App\Application\UseCase\Cart\CartCheckoutCoordinator;
use App\Application\UseCase\Cart\InvalidPromotionCodeException;
use App\Application\UseCase\Cart\RemoveCartItem;
use App\Application\UseCase\Cart\RemovePromotionCode;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Order\BuyerDetails;
use App\Domain\Commerce\Order\BuyerType;
use App\Entity\User;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The cart icon/dropdown in the site header (Stage 11 of the commerce
 * rollout plan, behind the `cart` flag). Mirrors NotificationTrayLiveComponent's
 * shape: a plain <details> disclosure in the template, no open/closed state
 * kept server-side. Every read (getItems(), getTotal(), ...) re-derives from
 * the customer's open cart rather than caching - "cart prices are quotes",
 * and this component re-renders after every action anyway.
 */
#[AsLiveComponent]
final class CartComponent extends AbstractController
{
    use DefaultActionTrait;

    private const string CURRENCY = 'PLN';

    #[LiveProp(writable: true)]
    public string $promotionCodeInput = '';

    #[LiveProp(writable: true)]
    public bool $termsAccepted = false;

    #[LiveProp(writable: true)]
    public bool $withdrawalAcknowledged = false;

    #[LiveProp(writable: true)]
    public string $buyerType = 'private';

    #[LiveProp(writable: true)]
    public string $taxIdentifier = '';

    #[LiveProp]
    public ?string $promotionCodeError = null;

    #[LiveProp]
    public ?string $checkoutError = null;

    /**
     * Set only for the response that actually completed a checkout - the
     * next render after that (cart is gone) won't have these, which is
     * exactly when the confirmation panel should stop showing.
     */
    #[LiveProp]
    public ?string $confirmedOrderNumber = null;

    #[LiveProp]
    public ?string $confirmedPaymentCode = null;

    /**
     * @var numeric-string|null
     */
    #[LiveProp]
    public ?string $confirmedTotalMinor = null;

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartItemRepositoryInterface $cartItemRepository,
        private readonly LessonRepositoryInterface $lessonRepository,
        private readonly ChildRepositoryInterface $childRepository,
        private readonly RemoveCartItem $removeCartItem,
        private readonly ApplyPromotionCode $applyPromotionCode,
        private readonly RemovePromotionCode $removePromotionCode,
        private readonly CartCheckoutCoordinator $checkoutCoordinator,
        private readonly WorkshopTermsCollector $workshopTermsCollector,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     schedule: string,
     *     ticketType: string,
     *     participantName: ?string,
     *     basePrice: Money,
     *     finalPrice: Money,
     *     hasDiscount: bool,
     * }>
     */
    public function getItems(): array
    {
        $cart = $this->getCart();
        if ($cart === null) {
            return [];
        }

        $items = [];
        foreach ($this->cartItemRepository->findByCart($cart->id) as $item) {
            $lesson = $this->lessonRepository->find($item->lessonId);
            $participant = $item->participantId !== null ? $this->childRepository->find($item->participantId) : null;

            $items[] = [
                'id' => (string) $item->id,
                'title' => $lesson?->getMetadata()->title ?? '—',
                'schedule' => $lesson?->schedule->format('Y-m-d H:i') ?? '',
                'ticketType' => $item->ticketType,
                'participantName' => $participant?->getName(),
                'basePrice' => Money::ofMinor($item->basePriceMinor, $item->currency),
                'finalPrice' => Money::ofMinor($item->finalPriceMinor, $item->currency),
                'hasDiscount' => $item->finalPriceMinor < $item->basePriceMinor,
            ];
        }

        return $items;
    }

    public function getItemCount(): int
    {
        return count($this->getItems());
    }

    public function getTotal(): Money
    {
        $total = Money::zero(self::CURRENCY);
        foreach ($this->getItems() as $item) {
            $total = $total->plus($item['finalPrice']);
        }

        return $total;
    }

    /** @return list<WorkshopTermsEvidence> */
    public function getWorkshopTerms(): array
    {
        $cart = $this->getCart();
        if ($cart === null) {
            return [];
        }

        return $this->workshopTermsCollector->collect($cart);
    }

    public function isCheckoutConsentRequired(): bool
    {
        return $this->checkoutCoordinator->isConsentRequired();
    }

    public function isWithdrawalAcknowledgementRequired(): bool
    {
        return $this->isCheckoutConsentRequired() && $this->buyerType === BuyerType::PRIVATE->value;
    }

    public function getPromotionCode(): ?string
    {
        return $this->getCart()?->promotionCode;
    }

    /**
     * No-op: LessonModal::addToCart() emits 'cart:updated' after adding an
     * item - LiveComponents re-render (and re-run every getXxx()) after
     * handling a listener, so listening is all that's needed to pick it up.
     */
    #[LiveAction]
    #[LiveListener('cart:updated')]
    public function refresh(): void {}

    #[LiveAction]
    public function remove(#[LiveArg] string $id): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        ($this->removeCartItem)(Ulid::fromString($id), $user->getId() ?? 0);
    }

    #[LiveAction]
    public function applyCode(): void
    {
        $this->promotionCodeError = null;
        $cart = $this->getCart();
        $user = $this->requireUser();
        $code = trim($this->promotionCodeInput);
        if ($cart === null || $user === null || $code === '') {
            return;
        }

        try {
            ($this->applyPromotionCode)($cart->id, $code, $user->getId() ?? 0);
            $this->promotionCodeInput = '';
        } catch (InvalidPromotionCodeException) {
            $this->promotionCodeError = 'cart.promotion_code.invalid';
        }
    }

    #[LiveAction]
    public function removeCode(): void
    {
        $cart = $this->getCart();
        $user = $this->requireUser();
        if ($cart === null || $user === null) {
            return;
        }

        ($this->removePromotionCode)($cart->id, $user->getId() ?? 0);
    }

    #[LiveAction]
    public function checkout(): void
    {
        $this->checkoutError = null;
        if (!$this->hasValidAcceptances()) {
            return;
        }
        $cart = $this->getCart();
        $user = $this->requireUser();
        if ($cart === null || $user === null) {
            $this->checkoutError = 'cart.checkout_error_empty';

            return;
        }

        $buyerDetails = $this->buyerDetails();
        if ($buyerDetails === null) {
            return;
        }
        $workshopTerms = $this->getWorkshopTerms();
        try {
            $completed = $this->checkoutCoordinator->complete($cart, $user, $buyerDetails, $workshopTerms);
        } catch (\LogicException) {
            $this->checkoutError = 'cart.checkout_error_empty';

            return;
        }

        $this->showCompletedCheckout($completed);
    }

    private function hasValidAcceptances(): bool
    {
        if (!$this->termsAccepted) {
            $this->checkoutError = 'cart.checkout_error_terms';

            return false;
        }
        if ($this->isWithdrawalAcknowledgementRequired() && !$this->withdrawalAcknowledged) {
            $this->checkoutError = 'cart.checkout_error_withdrawal';

            return false;
        }

        return true;
    }

    private function buyerDetails(): ?BuyerDetails
    {
        try {
            return BuyerDetails::fromInput($this->buyerType, $this->taxIdentifier);
        } catch (\InvalidArgumentException) {
            $this->checkoutError = 'cart.checkout_error_tax_identifier';

            return null;
        }
    }

    private function showCompletedCheckout(\App\Application\UseCase\Cart\CompletedCheckout $completed): void
    {
        $this->confirmedOrderNumber = $completed->order->getOrderNumber();
        $this->confirmedPaymentCode = $completed->paymentCode;
        $this->confirmedTotalMinor = (string) $completed->order->getTotalMinor();
        $this->termsAccepted = false;
        $this->withdrawalAcknowledged = false;
    }

    public function getConfirmedTotal(): ?Money
    {
        if ($this->confirmedTotalMinor === null) {
            return null;
        }

        return Money::ofMinor((int) $this->confirmedTotalMinor, self::CURRENCY);
    }

    private function getCart(): ?Cart
    {
        $user = $this->requireUser();
        if ($user === null) {
            return null;
        }

        return $this->cartRepository->findOpenForCustomer($user->getId() ?? 0, self::CURRENCY);
    }

    private function requireUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
