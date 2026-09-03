<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\PaymentRepositoryInterface;
use App\Entity\Child;
use App\Entity\Payment;
use App\Entity\User;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The panel's "Rozliczenia" billing ledger: every payment the user has made or
 * still owes, grouped by month, with a running "to pay" total and per-child
 * filtering. The quick-pay affordance itself is delegated to
 * OutstandingPaymentsComponent (rendered above the ledger).
 */
#[AsLiveComponent]
final class BillingComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public ?string $childId = null;

    #[LiveProp(writable: true, url: true)]
    public bool $onlyUnpaid = false;

    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly ChildRepositoryInterface $childRepository,
        private readonly Security $security,
    ) {}

    /**
     * @return list<Child>
     */
    public function getChildren(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? array_values($this->childRepository->findByOwner($user)) : [];
    }

    /**
     * Payments grouped by "YYYY-MM" of their settlement/creation date, newest
     * month first, newest payment first within each month.
     *
     * @return list<array{key: string, date: \DateTimeImmutable, payments: list<Payment>, total: Money}>
     *
     * @throws \Brick\Money\Exception\MoneyMismatchException
     * @throws \Brick\Math\Exception\MathException
     */
    public function getMonths(): array
    {
        $groups = [];

        foreach ($this->filteredPayments() as $payment) {
            $when = $payment->getPaidAt() ?? $payment->getCreatedAt();
            $key = $when->format('Y-m');
            $groups[$key] ??= ['key' => $key, 'date' => $when, 'payments' => [], 'total' => Money::zero('PLN')];
            $groups[$key]['payments'][] = $payment;
            $groups[$key]['total'] = $groups[$key]['total']->plus($payment->getAmount());
        }

        krsort($groups);

        return array_values($groups);
    }

    /**
     * @throws \Brick\Money\Exception\MoneyMismatchException
     * @throws \Brick\Math\Exception\MathException
     */
    public function getTotalOutstanding(): Money
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return Money::zero('PLN');
        }

        $total = Money::zero('PLN');
        foreach ($this->paymentRepository->findUnpaidForUser($user) as $payment) {
            $total = $total->plus($payment->getAmount()->minus($payment->getAmountPaid()));
        }

        return $total;
    }

    public function isOutstanding(Payment $payment): bool
    {
        return in_array($payment->getStatus(), [Payment::STATUS_PENDING, Payment::STATUS_EXPIRED], true);
    }

    public function paymentTitle(Payment $payment): ?string
    {
        $summary = $payment->getBookingsSummary();
        if ($summary !== '') {
            return $summary;
        }

        return $payment->getDescription();
    }

    public function paymentChildName(Payment $payment): ?string
    {
        foreach ($payment->getBookings() as $booking) {
            $name = $booking->getChild()?->getName();
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<Payment>
     */
    private function filteredPayments(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $payments = $this->paymentRepository->findForUser($user);

        if ($this->onlyUnpaid) {
            $payments = array_filter($payments, $this->isOutstanding(...));
        }

        $child = $this->childId;
        if ($child !== null && $child !== '') {
            $payments = array_filter($payments, static fn(Payment $p): bool => self::coversChild($p, $child));
        }

        return array_values($payments);
    }

    private static function coversChild(Payment $payment, string $childId): bool
    {
        foreach ($payment->getBookings() as $booking) {
            if ((string) $booking->getChild()?->getId() === $childId) {
                return true;
            }
        }

        return false;
    }
}
