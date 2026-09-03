<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Repository\PaymentRepositoryInterface;
use App\Application\Service\Payment\PaymentCodeGenerator;
use App\Entity\Payment;
use App\Entity\User;
use App\UserInterface\Http\Component\Concern\ToastableComponent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The customer panel's outstanding-payment / quick-pay panel — the kiddo
 * equivalent of ActiveNow's "unpaid balance" banner on the upcoming-classes
 * view, generalised to any unpaid reservation (pending or expired Payment).
 *
 * Renders nothing when the account is all paid. For a chosen payment it
 * mirrors LessonModal::resumePayment(): ensure a PaymentCode exists, show the
 * shared BLIK/transfer instructions, and poll until the transfer is matched
 * (settlement itself is async - MatchPaymentForTransferHandler).
 */
#[AsLiveComponent]
final class OutstandingPaymentsComponent extends AbstractController
{
    use DefaultActionTrait;
    use ToastableComponent;

    #[LiveProp(writable: true)]
    public ?string $watchedPaymentId = null;

    #[LiveProp]
    public ?string $paymentCode = null;

    #[LiveProp]
    public bool $awaitingPayment = false;

    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentCodeGenerator $paymentCodeGenerator,
        private readonly Security $security,
    ) {}

    /**
     * @return list<Payment>
     */
    public function getPayments(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->paymentRepository->findUnpaidForUser($user);
    }

    public function getWatchedPayment(): ?Payment
    {
        if ($this->watchedPaymentId === null || $this->watchedPaymentId === '') {
            return null;
        }

        try {
            $payment = $this->paymentRepository->find(Ulid::fromString($this->watchedPaymentId));
        } catch (\Throwable) {
            return null;
        }

        return $payment instanceof Payment ? $payment : null;
    }

    #[LiveAction]
    public function pay(#[LiveArg] string $paymentId): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        try {
            $payment = $this->paymentRepository->find(Ulid::fromString($paymentId));
        } catch (\Throwable) {
            return;
        }

        if (!$payment instanceof Payment || $payment->getUser()->getId() !== $user->getId()) {
            return;
        }

        if ($payment->getStatus() === Payment::STATUS_PAID) {
            return;
        }

        $paymentCode = $payment->getPaymentCode() ?? $this->paymentCodeGenerator->createFor($payment);

        $this->watchedPaymentId = (string) $payment->getId();
        $this->paymentCode = $paymentCode->getCode();
        $this->awaitingPayment = true;
    }

    #[LiveAction]
    public function cancelPay(): void
    {
        $this->watchedPaymentId = null;
        $this->paymentCode = null;
        $this->awaitingPayment = false;
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[LiveAction]
    public function refreshPaymentStatus(): void
    {
        if (!$this->awaitingPayment) {
            return;
        }

        $payment = $this->getWatchedPayment();
        if ($payment === null) {
            return;
        }

        if ($payment->getStatus() === Payment::STATUS_PAID) {
            $this->watchedPaymentId = null;
            $this->paymentCode = null;
            $this->awaitingPayment = false;
            $this->toast('panel.billing.payment_confirmed');
        }
    }
}
