<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony\Scheduler;

use App\Application\Command\CheckExpiredBookings;
use App\Application\Command\Notification\DailyLessonsReminder;
use App\Infrastructure\Symfony\Scheduler\MainSchedule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Scheduler\Generator\MessageGenerator;

#[Group('unit')]
class MainScheduleTest extends TestCase
{
    public function testCreateSchedule(): void
    {
        $schedule = new MainSchedule(new NullAdapter())
            ->getSchedule();

        self::assertCount(7, $schedule->getRecurringMessages());
    }

    public function testRecurringMessagesWithDatesAreCreatedDynamically(): void
    {
        $schedule = new MainSchedule(new ArrayAdapter())
            ->getSchedule();

        $messageGenerator = new MessageGenerator($schedule, 'name', $mockClock = new MockClock());

        $mockClock->modify('+1 day');

        // Collect datetimes for each message type
        $reminderDates = [];
        $expiredBookingDates = [];

        // Generate messages multiple times to simulate dynamic creation
        for ($i = 0; $i < 5; $i++) {
            foreach ($messageGenerator->getMessages() as $message) {
                if ($message instanceof Envelope) {
                    $message = $message->getMessage();
                }

                if ($message instanceof DailyLessonsReminder) {
                    $reminderDates[] = $message->date;
                }
                if ($message instanceof CheckExpiredBookings) {
                    $expiredBookingDates[] = $message->expirationTime;
                }
            }
            $mockClock->modify('+1 day');
        }

        // Assert that consecutive datetimes are not equal for each type
        for ($i = 1; $i < count($reminderDates); $i++) {
            self::assertNotEquals(
                $reminderDates[$i - 1],
                $reminderDates[$i],
                'DailyLessonsReminder dates should be dynamically created and not equal'
            );
        }
        for ($i = 1; $i < count($expiredBookingDates); $i++) {
            self::assertNotEquals(
                $expiredBookingDates[$i - 1],
                $expiredBookingDates[$i],
                'CheckExpiredBookings expirationTime should be dynamically created and not equal'
            );
        }
    }
}
