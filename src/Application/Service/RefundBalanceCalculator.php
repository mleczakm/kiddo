<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Payment;
use App\Entity\RefundRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class RefundBalanceCalculator
{
    public function __construct(
        private RefundRequestRepositoryInterface $refunds,
        private EntityManagerInterface $em,
    ) {}

    public function refundableForBooking(Payment $payment, ?Ulid $orderLineId): int
    {
        $captured = $payment->getAmount()->getMinorAmount()->toInt();
        if ($orderLineId !== null) {
            $line = $this->em->find(OrderLine::class, $orderLineId);
            if ($line instanceof OrderLine) {
                $captured = $line->getFinalPriceMinor();
            }
        }

        return max(0, $captured - $this->approved($payment, $orderLineId));
    }

    public function approved(Payment $payment, ?Ulid $orderLineId = null): int
    {
        $total = 0;
        foreach ($this->refunds->findBy([
            'payment' => $payment,
            'status' => RefundRequest::STATUS_APPROVED,
        ]) as $refund) {
            if ($orderLineId !== null && !$refund->getOrderLineId()?->equals($orderLineId)) {
                continue;
            }
            $total += $refund->getApprovedAmountMinor();
        }
        return $total;
    }
}
