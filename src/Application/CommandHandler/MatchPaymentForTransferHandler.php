<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Application\Repository\PaymentCodeRepositoryInterface;
use App\Application\Workflow\PaymentStateMachineInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MatchPaymentForTransferHandler
{
    public function __construct(
        private PaymentCodeRepositoryInterface $paymentCodeRepository,
        private PaymentStateMachineInterface $paymentStateMachine,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(MatchPaymentForTransfer $command): void
    {
        $transfer = $command->transfer;

        if ($transfer->getPayment() !== null) {
            // Already resolved (e.g. reprocessed via the past-transfers rematch
            // sweep) - do not re-run matching against a transfer that's already
            // attached to a payment.
            return;
        }

        $title = $command->transfer->title;

        foreach ($this->tokenizeTitle($title) as $word) {
            $paymentCode = $this->paymentCodeRepository->findOneByCode($word);

            if ($paymentCode) {
                $payment = $paymentCode->getPayment();
                $payment->addTransfer($transfer);

                if ($this->paymentStateMachine->can($payment, 'pay')) {
                    if ($payment->getAmountPaid()->isGreaterThan($payment->getAmount())) {
                        $payment->flagForReview();
                    }

                    $this->paymentStateMachine->apply($payment, 'pay');
                }

                return;
            }
        }

        $this->messageBus->dispatch(new TransferNotMatchedCommand($transfer));
    }

    /**
     * @return \Generator<int, string>
     */
    private function tokenizeTitle(string $title): \Generator
    {
        $tokens = array_values(array_filter(
            explode(' ', preg_replace('/[^A-Za-z0-9]/', ' ', mb_strtoupper($title)) ?? ''),
            static fn(string $word): bool => $word !== '',
        ));

        $emitted = [];

        foreach ($tokens as $token) {
            yield $emitted[] = $token;
        }

        $count = count($tokens);

        for ($i = 0; $i < ($count - 1); $i++) {
            yield $emitted[] = $tokens[$i] . $tokens[$i + 1];
        }

        foreach ($emitted as $token) {
            $substituted = str_replace('0', 'O', $token);

            if (!hash_equals($substituted, $token)) {
                yield $substituted;
            }

            $substituted = str_replace('O', '0', $token);

            if (!hash_equals($substituted, $token)) {
                yield $substituted;
            }

            // 3-arg strtr swaps '0' and 'O' in a single pass (no re-translation),
            // and sidesteps the numeric-string-key typing issue the array form has.
            $substituted = strtr($token, '0O', 'O0');

            if (!hash_equals($substituted, $token)) {
                yield $substituted;
            }
        }
    }
}
