<?php

declare(strict_types=1);

namespace App\Application\Service\Subscription;

use App\Application\Service\BookingFactory;
use App\Application\Service\Payment\PaymentCodeGenerator;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\Subscription;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use Brick\Math\Exception\MathException;
use Brick\Money\Exception\MoneyException;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Issues one month's charge for a subscription: a pending Payment (so it flows
 * through transfer-matching / the billing ledger / quick-pay) plus a Booking
 * over that month's remaining series lessons, linked back to the subscription.
 * Marks the period charged so re-runs are idempotent. Shared by
 * SubscribeToSeries (first month) and IssueSubscriptionCharges (later months).
 */
final readonly class SubscriptionBillingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookingFactory $bookingFactory,
        private PaymentCodeGenerator $paymentCodeGenerator,
    ) {}

    /**
     * @throws \RuntimeException when the amount is invalid or no payment code can be allocated
     */
    public function chargeForPeriod(Subscription $subscription, \DateTimeImmutable $period): ?Payment
    {
        if (!$subscription->needsChargeFor($period)) {
            return null;
        }

        $anchor = $this->anchorLesson($subscription, $period);
        if ($anchor === null) {
            // No sessions this month (e.g. a break month) - nothing to bill.
            $subscription->markCharged($period);

            return null;
        }

        try {
            $amount = Money::ofMinor($subscription->getMonthlyRateMinor(), $subscription->getCurrency());
        } catch (MoneyException|MathException $e) {
            throw new \RuntimeException('Invalid subscription amount.', 0, $e);
        }

        $payment = new Payment(
            $subscription->getUser(),
            $amount,
            null,
            sprintf('%s · %s', $anchor->getMetadata()->title, $period->format('m.Y')),
        );
        new PaymentCode($payment, $this->paymentCodeGenerator->generateAvailable());

        $option = new TicketOption(
            TicketType::MONTHLY,
            $amount,
            'Abonament miesięczny',
            TicketReschedulePolicy::NOT_ALLOWED,
        );
        $booking = $this->bookingFactory->createFrom($anchor, $option, $subscription->getUser(), $payment);
        $booking->setSubscriptionId($subscription->getId());
        if ($subscription->getChild() !== null) {
            $booking->setChild($subscription->getChild());
        }

        $this->em->persist($payment);
        $this->em->persist($booking);

        $subscription->markCharged($period);

        return $payment;
    }

    private function anchorLesson(Subscription $subscription, \DateTimeImmutable $period): ?Lesson
    {
        $key = $period->format('Y-m');
        $candidates = array_filter(
            $subscription->getSeries()->lessons->toArray(),
            static fn(Lesson $lesson): bool => $lesson->schedule->format('Y-m') === $key && $lesson->future(),
        );
        usort($candidates, static fn(Lesson $a, Lesson $b): int => $a->schedule <=> $b->schedule);

        return $candidates[0] ?? null;
    }
}
