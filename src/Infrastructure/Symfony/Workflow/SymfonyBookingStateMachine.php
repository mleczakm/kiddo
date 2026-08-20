<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Workflow;

use App\Application\Workflow\BookingStateMachineInterface;
use App\Entity\Booking;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

readonly class SymfonyBookingStateMachine implements BookingStateMachineInterface
{
    public function __construct(
        #[Autowire(service: 'state_machine.booking')]
        private WorkflowInterface $workflow,
    ) {}

    #[\Override]
    public function can(Booking $booking, string $transition): bool
    {
        return $this->workflow->can($booking, $transition);
    }

    /**
     * @throws \Symfony\Component\Workflow\Exception\LogicException
     */
    #[\Override]
    public function apply(Booking $booking, string $transition, array $context = []): void
    {
        $this->workflow->apply($booking, $transition, $context);
    }
}
