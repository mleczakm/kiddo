<?php

declare(strict_types=1);

namespace App\Application\Workflow;

/**
 * Port to the Symfony Workflow state machines declared in config/packages/workflow.yaml,
 * resolved from the subject's class. Use it for every lifecycle that does not need a
 * dedicated, richer port (Booking and Payment keep theirs).
 *
 * The workflow definition is the only place a lifecycle is described: to find out which
 * states and transitions exist, read the YAML (or docs/STATE_MACHINES.md); never compare
 * `->status` against string literals in application code.
 */
interface StateMachinesInterface
{
    public function can(object $subject, string $transition): bool;

    /**
     * @param array<string, mixed> $context
     *
     * A transition that is not enabled throws Symfony's workflow LogicException - a programming
     * error (guard with can() or use applyIfPossible()), so it is deliberately not declared.
     */
    public function apply(object $subject, string $transition, array $context = []): void;

    /**
     * Apply the transition when it is enabled; return whether it was.
     * The no-op-if-illegal behaviour the hand-rolled status methods used to have.
     *
     * @param array<string, mixed> $context
     */
    public function applyIfPossible(object $subject, string $transition, array $context = []): bool;
}
