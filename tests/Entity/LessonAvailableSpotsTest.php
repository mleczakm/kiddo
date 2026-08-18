<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Booking;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class LessonAvailableSpotsTest extends TestCase
{
    #[Test]
    public function pendingBookingsReduceAvailableSpots(): void
    {
        $lesson = LessonAssembler::new()->withCapacity(2)->assemble();

        $pending = BookingAssembler::new()->withLessons($lesson)->withStatus(Booking::STATUS_PENDING)->assemble();
        $lesson->addBooking($pending);

        self::assertSame(1, $lesson->getAvailableSpots());
        self::assertTrue($pending->occupiesSeat());
    }

    #[Test]
    public function cancelledBookingsDoNotOccupySeats(): void
    {
        $lesson = LessonAssembler::new()->withCapacity(2)->assemble();

        $cancelled = BookingAssembler::new()->withLessons($lesson)->withStatus(Booking::STATUS_CANCELLED)->assemble();
        $lesson->addBooking($cancelled);

        self::assertSame(2, $lesson->getAvailableSpots());
        self::assertFalse($cancelled->occupiesSeat());
    }
}
