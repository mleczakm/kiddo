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

    /**
     * @param list<WorkshopTermsEvidence> $workshopTerms
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Math\Exception\NumberFormatException
     * @throws \Brick\Math\Exception\RoundingNecessaryException
     * @throws \Brick\Money\Exception\UnknownCurrencyException
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Random\RandomException
     * @throws \RuntimeException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Doctrine\DBAL\Exception
     */
    public function complete(
        Cart $cart,
        User $user,
        BuyerDetails $buyerDetails,
        array $workshopTerms,
    ): CompletedCheckout {
        $order = ($this->checkoutCart)($cart->id, $user->getId() ?? 0, buyerDetails: $buyerDetails);
        $payment = $this->paymentRepository->findOneBy(['orderId' => $order->getId()]);
        $paymentCode = $payment instanceof Payment ? $payment->getPaymentCode()?->getCode() : null;

        // Recorded last, after everything the confirmation needs has been read:
        // consent recording runs in its own bus transaction and CheckoutConsentManager
        // contains any failure, so it stays off the checkout's critical path.
        $this->consentManager->record($user, $order, $workshopTerms);

        return new CompletedCheckout($order, $paymentCode);
    }
}
