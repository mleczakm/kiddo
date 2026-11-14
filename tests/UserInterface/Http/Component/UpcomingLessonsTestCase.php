<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\UserInterface\Http\Component\UpcomingLessons;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\NativeClock;

abstract class UpcomingLessonsTestCase extends TestCase
{
    protected LessonRepository&MockObject $lessonRepository;

    protected BookingRepository&MockObject $bookingRepository;

    protected Security&MockObject $security;

    protected UpcomingLessons $component;

    #[\Override]
    protected function setUp(): void
    {
        $this->lessonRepository = $this->createMock(LessonRepository::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->component = new UpcomingLessons($this->lessonRepository, $this->bookingRepository, $this->security);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }
}
