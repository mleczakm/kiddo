<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Application\Event\ActivityOccurred;
use App\Entity\ActivityType;
use App\Infrastructure\EventSubscriber\SentryActivityMetricsSubscriber;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class SentryActivityMetricsSubscriberTest extends TestCase
{
    private MetricsRecorderInterface&MockObject $metrics;

    private SentryActivityMetricsSubscriber $subscriber;

    #[\Override]
    protected function setUp(): void
    {
        $this->metrics = $this->createMock(MetricsRecorderInterface::class);
        $this->subscriber = new SentryActivityMetricsSubscriber($this->metrics);
    }

    public function testOnActivityOccurredTracksTotalAndTypeCounters(): void
    {
        $event = new ActivityOccurred(ActivityType::BOOKING_CANCELLED, 'Booking cancelled');

        $calls = [];
        $this->metrics
            ->expects($this->exactly(2))
            ->method('count')
            ->willReturnCallback(static function (string $name, int|float $value) use (&$calls): void {
                $calls[] = [$name, $value];
            });

        $this->subscriber->onActivityOccurred($event);

        static::assertSame(
            [
                ['activities.total',             1],
                ['activities.booking_cancelled', 1],
            ],
            $calls,
        );
    }
}
