<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Workflow\StateMachinesInterface;
use App\Entity\Lesson;

/**
 * Staff "toggle" control: cancel an active lesson or re-open a cancelled one
 * through the `lesson` state machine. Does not flush - callers own the unit of work.
 */
final readonly class ToggleLessonCancellation
{
    public function __construct(
        private StateMachinesInterface $stateMachines,
    ) {}

    public function __invoke(Lesson $lesson): void
    {
        $this->stateMachines->apply($lesson, $lesson->isCancelled() ? 'reactivate' : 'cancel');
    }
}
