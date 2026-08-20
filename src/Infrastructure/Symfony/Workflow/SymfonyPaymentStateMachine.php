<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Workflow;

use App\Application\Workflow\PaymentStateMachineInterface;
use App\Entity\Payment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

readonly class SymfonyPaymentStateMachine implements PaymentStateMachineInterface
{
    public function __construct(
        #[Autowire(service: 'state_machine.payment')]
        private WorkflowInterface $workflow,
    ) {}

    #[\Override]
    public function can(Payment $payment, string $transition): bool
    {
        return $this->workflow->can($payment, $transition);
    }

    /**
     * @throws \Symfony\Component\Workflow\Exception\LogicException
     */
    #[\Override]
    public function apply(Payment $payment, string $transition, array $context = []): void
    {
        $this->workflow->apply($payment, $transition, $context);
    }
}
