<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Consent\CheckoutConsentManager;
use App\Application\Consent\WorkshopTermsEvidence;
use App\Application\Repository\PaymentRepositoryInterface;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Order\BuyerDetails;
use App\Entity\Payment;
use App\Entity\User;

final readonly class CartCheckoutCoordinator
{
    public function __construct(
        private CheckoutCart $checkoutCart,
        private PaymentRepositoryInterface $paymentRepository,
        private CheckoutConsentManager $consentManager,
    ) {}

    public function isConsentRequired(): bool
    {
        return $this->consentManager->isRequired();
    }

    /** @param list<WorkshopTermsEvidence> $workshopTerms */
    public function complete(
        Cart $cart,
        User $user,
        BuyerDetails $buyerDetails,
        array $workshopTerms,
    ): CompletedCheckout {
        $order = ($this->checkoutCart)($cart->id, $user->getId() ?? 0, buyerDetails: $buyerDetails);
        $this->consentManager->record($user, $order, $workshopTerms);
        $payment = $this->paymentRepository->findOneBy(['orderId' => $order->getId()]);

        return new CompletedCheckout(
            $order,
            $payment instanceof Payment ? $payment->getPaymentCode()?->getCode() : null,
        );
    }
}
