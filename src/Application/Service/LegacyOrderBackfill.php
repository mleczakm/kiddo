<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\PaymentRepositoryInterface;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\TicketType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class LegacyOrderBackfill
{
    public function __construct(
        private PaymentRepositoryInterface $payments,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return array{paymentsProcessed: int, ordersCreated: int, unmatchedBookings: int, amountDifferenceMinor: int, currencyInconsistencies: int}
     *
     * @throws \ArithmeticError
     * @throws \Brick\Math\Exception\MathException
     * @throws \DivisionByZeroError
     */
    public function run(int $limit = 100): array
    {
        $report = [
            'paymentsProcessed' => 0,
            'ordersCreated' => 0,
            'unmatchedBookings' => 0,
            'amountDifferenceMinor' => 0,
            'currencyInconsistencies' => 0,
        ];
        $payments = $this->payments->findBy(['orderId' => null], ['createdAt' => 'ASC'], $limit);
        foreach ($payments as $payment) {
            ++$report['paymentsProcessed'];
            $bookings = $payment->getBookings()->toArray();
            if ($bookings === []) {
                ++$report['unmatchedBookings'];
                continue;
            }

            $currency = $payment->getAmount()->getCurrency()->getCurrencyCode();
            if ($currency !== 'PLN') {
                ++$report['currencyInconsistencies'];
            }
            $total = $payment->getAmount()->getMinorAmount()->toInt();
            $orderId = new Ulid();
            $order = new CustomerOrder(
                $orderId,
                'LG-' . $orderId->toBase32(),
                $payment->getUser()->getId() ?? 0,
                $this->orderStatus($payment),
                $currency,
                $total,
                0,
                $total,
                $payment->getCreatedAt(),
                null,
                'legacy-payment-' . (string) $payment->getId(),
                CustomerOrder::SOURCE_LEGACY,
            );
            $this->em->persist($order);

            $base = intdiv($total, count($bookings));
            $remainder = $total - ($base * count($bookings));
            $allocated = 0;
            foreach ($bookings as $index => $booking) {
                $lineAmount = $base + ($index === 0 ? $remainder : 0);
                $allocated += $lineAmount;
                $line = $this->line($orderId, $booking, $lineAmount, $currency);
                $booking->setOrderLineId($line->getId());
                $this->em->persist($line);
            }
            $report['amountDifferenceMinor'] += $total - $allocated;
            $payment->setOrderId($orderId);
            ++$report['ordersCreated'];
            $this->em->flush();
        }

        return $report;
    }

    private function line(Ulid $orderId, Booking $booking, int $amount, string $currency): OrderLine
    {
        $lesson = $booking->getLesson();
        return new OrderLine(
            new Ulid(),
            $orderId,
            $booking->isCarnet() ? null : $lesson?->getId(),
            $booking->isCarnet() ? $lesson?->getSeries()?->getId() : null,
            $booking->isCarnet() ? TicketType::CARNET_4->value : TicketType::ONE_TIME->value,
            $booking->getTitle() !== '' ? $booking->getTitle() : 'Legacy booking',
            $lesson?->schedule->format('Y-m-d H:i'),
            $booking->getChild()?->getId(),
            $amount,
            $amount,
            $currency,
            null,
            $booking->getId(),
            ['adjustments' => [], 'rejectedRuleReasons' => ['pricing_provenance:legacy_unknown']],
        );
    }

    private function orderStatus(Payment $payment): string
    {
        return match ($payment->getStatus()) {
            Payment::STATUS_PAID,
            Payment::STATUS_REFUND_REQUESTED,
            Payment::STATUS_REFUNDED,
                => CustomerOrder::STATUS_PAID,
            Payment::STATUS_CANCELLED => CustomerOrder::STATUS_CANCELLED,
            Payment::STATUS_EXPIRED => CustomerOrder::STATUS_EXPIRED,
            default => CustomerOrder::STATUS_PLACED,
        };
    }
}
