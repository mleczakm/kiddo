<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Entity\PaymentCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class MatchPaymentForTransferHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkflowInterface $paymentStateMachine,
        private MessageBusInterface $messageBus
    ) {}

    public function __invoke(MatchPaymentForTransfer $command): void
    {
        $transfer = $command->transfer;
        $title = $command->transfer->title;

        foreach ($this->tokenizeTitle($title) as $word) {
            $paymentCode = $this->entityManager->getRepository(PaymentCode::class)
                ->findOneBy([
                    'code' => $word,
                ]);

            if ($paymentCode) {
                $payment = $paymentCode->getPayment();
                $payment->addTransfer($transfer);

                if ($this->paymentStateMachine->can($payment, 'pay')) {
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
            fn(string $word): bool => $word !== ''
        ));

        foreach ($tokens as $token) {
            yield $token;
        }

        $count = count($tokens);

        for ($i = 0; $i < $count - 1; $i++) {
            yield $tokens[$i] . $tokens[$i + 1];
        }
    }
}
