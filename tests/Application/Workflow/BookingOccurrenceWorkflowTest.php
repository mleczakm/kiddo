<?php

declare(strict_types=1);

namespace App\Tests\Application\Workflow;

use App\Application\Workflow\StateMachinesInterface;
use App\Entity\Booking;
use App\Entity\BookingOccurrence;
use App\Entity\Lesson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class BookingOccurrenceWorkflowTest extends KernelTestCase
{
    private StateMachinesInterface $stateMachines;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $stateMachines = self::getContainer()->get(StateMachinesInterface::class);
        \assert($stateMachines instanceof StateMachinesInterface);
        $this->stateMachines = $stateMachines;
    }

    /**
     * BookingOccurrence transitions are driven from inside the Booking aggregate (which cannot
     * reach the container), so its methods keep their own guards. This pins them to the
     * workflow definition: every method must succeed exactly where the workflow says it may.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function occurrenceTransitions(): iterable
    {
        foreach ([
            BookingOccurrence::STATUS_RESERVED,
            BookingOccurrence::STATUS_CONFIRMED,
            BookingOccurrence::STATUS_ATTENDED,
            BookingOccurrence::STATUS_CANCELLED,
            BookingOccurrence::STATUS_RESCHEDULED,
        ] as $state) {
            foreach (['confirm', 'attend', 'cancel', 'reschedule', 'reactivate'] as $transition) {
                yield "$transition from $state" => [$state, $transition];
            }
        }
    }

    #[DataProvider('occurrenceTransitions')]
    public function testBookingOccurrenceMethodsMatchTheWorkflowDefinition(string $state, string $transition): void
    {
        $lesson = $this->createStub(Lesson::class);
        $occurrence = new BookingOccurrence($this->createStub(Booking::class), $lesson, $state);
        $enabled = $this->stateMachines->can($occurrence, $transition);

        match ($transition) {
            'confirm' => $occurrence->confirm(),
            'attend' => $occurrence->attend(),
            'cancel' => $occurrence->cancel(null, null),
            'reschedule' => $occurrence->rescheduleTo($this->createStub(Lesson::class)),
            'reactivate' => $occurrence->reactivate(),
            default => throw new \LogicException('Unknown transition ' . $transition),
        };

        // No occurrence transition is a self-loop, so "the method worked" == "the status moved".
        static::assertSame(
            $enabled,
            $occurrence->getStatus() !== $state,
            sprintf('"%s" from "%s": workflow enabled=%s', $transition, $state, var_export($enabled, true)),
        );
    }
}
