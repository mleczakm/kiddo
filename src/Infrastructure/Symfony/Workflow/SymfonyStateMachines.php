<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Workflow;

use App\Application\Workflow\StateMachinesInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\Registry;

readonly class SymfonyStateMachines implements StateMachinesInterface
{
    public function __construct(
        #[Autowire(service: 'workflow.registry')]
        private Registry $registry,
    ) {}

    #[\Override]
    public function can(object $subject, string $transition): bool
    {
        return $this->registry->get($subject)->can($subject, $transition);
    }

    /**
     * @throws \Symfony\Component\Workflow\Exception\LogicException
     */
    #[\Override]
    public function apply(object $subject, string $transition, array $context = []): void
    {
        $this->registry->get($subject)->apply($subject, $transition, $context);
    }

    /**
     * @throws \Symfony\Component\Workflow\Exception\LogicException
     */
    #[\Override]
    public function applyIfPossible(object $subject, string $transition, array $context = []): bool
    {
        $workflow = $this->registry->get($subject);
        if (!$workflow->can($subject, $transition)) {
            return false;
        }

        $workflow->apply($subject, $transition, $context);

        return true;
    }
}
