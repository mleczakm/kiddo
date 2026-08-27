<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use Aeon\Calendar\TimeUnit;
use App\Application\Command\CheckExpiredPayments;
use App\Application\Repository\PaymentRepositoryInterface;
use App\Application\Service\WorkingDaysDeadlineCalculator;
use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\Payment;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckExpiredPaymentsHandler
{
    /**
     * Payments get 24 working hours (Monday-Friday, excluding Polish public
     * holidays) to be settled before they expire - weekend and holiday time
     * doesn't count, since a bank transfer can't settle until the next
     * business day.
     */
    private const int EXPIRATION_WORKING_HOURS = 24;

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentStateMachineInterface $paymentStateMachine,
        private WorkingDaysDeadlineCalculator $workingDaysDeadlineCalculator,
    ) {}

    /**
     * @throws \Aeon\Calendar\Exception\InvalidArgumentException
     */
    public function __invoke(CheckExpiredPayments $command): void
    {
        // A necessary (not sufficient) pre-filter: a payment can only have
        // accrued EXPIRATION_WORKING_HOURS of working time if at least that
        // many real hours have passed, since weekends and holidays only pause
        // the clock. The exact per-payment deadline is checked below.
        $naiveCutoff = $command->referenceTime->modify(sprintf('-%d hours', self::EXPIRATION_WORKING_HOURS));

        foreach ($this->paymentRepository->findExpiredPendingPayments($naiveCutoff) as $payment) {
            $deadline = $this->workingDaysDeadlineCalculator->addWorkingTime(
                $payment->getCreatedAt(),
                TimeUnit::hours(self::EXPIRATION_WORKING_HOURS),
            );

            if ($deadline > $command->referenceTime) {
                continue;
            }

            if ($this->paymentStateMachine->can($payment, Payment::TRANSITION_EXPIRE)) {
                $this->paymentStateMachine->apply($payment, Payment::TRANSITION_EXPIRE);
            }
        }
    }
}
