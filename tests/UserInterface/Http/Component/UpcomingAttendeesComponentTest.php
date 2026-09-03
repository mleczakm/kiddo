<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Entity\Booking;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\UpcomingAttendeesComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class UpcomingAttendeesComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $entityManager;

    private LessonRepository $lessonRepository;

    private KernelBrowser $client;

    public function testCanRenderWithUpcomingLessons(): void
    {
        $futureDate = Clock::get()->now()->modify('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(5)->assemble())
            ->withSchedule($futureDate)
            ->assemble();

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);
        $rendered = (string) $testComponent->render();
        static::assertStringContainsString($lesson->getMetadata()->title, $rendered);
        static::assertStringContainsString('5', $rendered);
    }

    public function testIncreaseCapacity(): void
    {
        $futureDate = Clock::get()->now()->modify('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(5)->assemble())
            ->withSchedule($futureDate)
            ->assemble();

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);

        $testComponent->call('increaseCapacity', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        $updatedLesson = $this->lessonRepository->find($lesson->getId()) ?? throw new \LogicException(
            'Lesson not found',
        );
        static::assertSame(6, $updatedLesson->getMetadata()->capacity);
    }

    public function testDecreaseCapacity(): void
    {
        $futureDate = Clock::get()->now()->modify('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(1)->assemble())
            ->withSchedule($futureDate)
            ->assemble();

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);

        $testComponent->call('decreaseCapacity', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        $updatedLesson = $this->lessonRepository->find($lesson->getId()) ?? throw new \LogicException(
            'Lesson not found',
        );
        static::assertSame(0, $updatedLesson->getMetadata()->capacity);
    }

    public function testCannotDecreaseCapacityBelowBookings(): void
    {
        $futureDate = Clock::get()->now()->modify('+1 day');

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(3)->assemble())
            ->withSchedule($futureDate)
            ->assemble();

        $user = UserAssembler::new()->assemble();

        $booking1 = BookingAssembler::new()->withStatus('active')->withUser($user)->withLessons($lesson)->assemble();
        $booking2 = BookingAssembler::new()->withStatus('active')->withLessons($lesson)->withUser($user)->assemble();
        $booking3 = BookingAssembler::new()->withStatus('active')->withUser($user)->withLessons($lesson)->assemble();

        $lesson->addBooking($booking1);
        $lesson->addBooking($booking2);
        $lesson->addBooking($booking3);

        $this->entityManager->persist($user);
        $this->entityManager->persist($booking1);
        $this->entityManager->persist($booking2);
        $this->entityManager->persist($booking3);

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);

        $testComponent->call('decreaseCapacity', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        $updatedLesson = $this->lessonRepository->find($lesson->getId()) ?? throw new \LogicException(
            'Lesson not found',
        );
        static::assertSame(3, $updatedLesson->getMetadata()->capacity);
    }

    public function testCancelledBookingsAreShownAsInactiveReservations(): void
    {
        $futureDate = Clock::get()->now()->modify('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(5)->assemble())
            ->withSchedule($futureDate)
            ->assemble();

        $user = UserAssembler::new()->assemble();

        $activeBooking = BookingAssembler::new()
            ->withStatus('active')
            ->withUser($user)
            ->withLessons($lesson)
            ->assemble();

        $cancelledBooking = BookingAssembler::new()
            ->withStatus('cancelled')
            ->withUser($user)
            ->withLessons($lesson)
            ->assemble();

        $lesson->addBooking($activeBooking);
        $lesson->addBooking($cancelledBooking);

        $this->entityManager->persist($user);
        $this->entityManager->persist($activeBooking);
        $this->entityManager->persist($cancelledBooking);
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);

        $rendered = (string) $testComponent->render();

        static::assertStringContainsString(
            (string) $activeBooking->getId(),
            $rendered,
            'Active booking should be shown',
        );

        // Cancelled bookings are no longer hidden behind a toggle — they render
        // as an "inactive reservation" (struck through / grouped).
        static::assertStringContainsString(
            (string) $cancelledBooking->getId(),
            $rendered,
            'Cancelled booking should be rendered as an inactive reservation',
        );
        static::assertStringContainsString(
            'line-through',
            $rendered,
            'Cancelled booking should be visually distinguished with a strikethrough',
        );
    }

    public function testConfirmFastBookingWritesAnActivityLogEntry(): void
    {
        $futureDate = Clock::get()->now()->modify('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(5)->assemble())
            ->withSchedule($futureDate)
            ->assemble();

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);
        $testComponent->set('selectedLessonId', (string) $lesson->getId());
        $testComponent->set('customerEmail', 'quickbook@example.com');
        $testComponent->set('customerName', 'Kasia Wiśniewska');
        $testComponent->call('confirmFastBooking');

        $activityLogs = $this->entityManager
            ->getRepository(ActivityLog::class)
            ->findBy([
                'type' => ActivityType::BOOKING_CREATED,
            ]);

        static::assertCount(1, $activityLogs);
        static::assertStringContainsString('Kasia Wiśniewska', $activityLogs[0]->getTitle());
        static::assertSame($lesson->getMetadata()->title, $activityLogs[0]->getSummary());
    }

    public function testMarkPaidWritesAnActivityLogEntry(): void
    {
        $futureDate = Clock::get()->now()->modify('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(5)->assemble())
            ->withSchedule($futureDate)
            ->assemble();
        $user = UserAssembler::new()->withName('Piotr Zając')->assemble();
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $this->entityManager->persist($lesson);
        $this->entityManager->persist($user);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);
        $testComponent->call('markPaid', [
            'bookingId' => (string) $booking->getId(),
        ]);

        $activityLogs = $this->entityManager
            ->getRepository(ActivityLog::class)
            ->findBy([
                'type' => ActivityType::PAYMENT_MARKED_PAID,
            ]);

        static::assertCount(1, $activityLogs);
        static::assertSame($user->getId(), $activityLogs[0]->getSubject()?->getId());
        static::assertStringContainsString('Piotr Zając', $activityLogs[0]->getTitle());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->lessonRepository = self::getContainer()->get(LessonRepository::class);
    }
}
