<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Message\CancelLessonBooking;
use App\Message\RescheduleLessonBooking;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminBookingsComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[Group('functional')]
final class AdminBookingsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    use InteractsWithMessenger;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCancelLessonDispatchesCancelCommandAsTheLoggedInAdmin(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->assemble();
        $this->em->persist($customer);

        $lesson = LessonAssembler::new()
            ->withTitle('Sensoplastyka')
            ->withSchedule(Clock::get()->now()->modify('+1 day'))
            ->assemble();
        $this->em->persist($lesson);

        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $this->em->persist($booking);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminBookingsComponent::class, client: $this->client);
        $component->call('cancelLesson', [
            'bookingId' => (string) $booking->getId(),
            'lessonId' => (string) $lesson->getId(),
        ]);

        $this->transport('async')->queue()->assertContains(CancelLessonBooking::class, 1);
    }

    public function testRescheduleFlowDispatchesRescheduleCommand(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->assemble();
        $this->em->persist($customer);

        $oldLesson = LessonAssembler::new()
            ->withTitle('Zajęcia A')
            ->withSchedule(Clock::get()->now()->modify('+1 day'))
            ->assemble();
        $newLesson = LessonAssembler::new()
            ->withTitle('Zajęcia B')
            ->withSchedule(Clock::get()->now()->modify('+2 days'))
            ->assemble();
        $this->em->persist($oldLesson);
        $this->em->persist($newLesson);

        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons($oldLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $this->em->persist($booking);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminBookingsComponent::class, client: $this->client);
        $component->call('startReschedule', [
            'bookingId' => (string) $booking->getId(),
            'lessonId' => (string) $oldLesson->getId(),
        ]);

        /** @var AdminBookingsComponent $adminBookingsComponent */
        $adminBookingsComponent = $component->component();
        self::assertTrue($adminBookingsComponent->isReschedulingLesson(
            (string) $booking->getId(),
            (string) $oldLesson->getId(),
        ));

        $component->set('newLessonId', (string) $newLesson->getId());
        $component->call('reschedule');

        $this->transport('async')->queue()->assertContains(RescheduleLessonBooking::class, 1);

        // Picker state is cleared after a successful dispatch
        /** @var AdminBookingsComponent $adminBookingsComponent */
        $adminBookingsComponent = $component->component();
        self::assertFalse($adminBookingsComponent->isReschedulingLesson(
            (string) $booking->getId(),
            (string) $oldLesson->getId(),
        ));
    }

    public function testHostOnlySeesBookingsForLessonsTheyInstruct(): void
    {
        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $this->em->persist($host);

        $customer = UserAssembler::new()->assemble();
        $this->em->persist($customer);

        $ownLesson = LessonAssembler::new()->withTitle('Zajęcia prowadzącego')->assemble();
        $ownLesson->addInstructor($host);
        $this->em->persist($ownLesson);

        $otherLesson = LessonAssembler::new()->withTitle('Cudze zajęcia')->assemble();
        $this->em->persist($otherLesson);

        $ownBooking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons($ownLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $otherBooking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons($otherLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $this->em->persist($ownBooking);
        $this->em->persist($otherBooking);
        $this->em->flush();

        $this->client->loginUser($host);

        $component = $this->createLiveComponent(name: AdminBookingsComponent::class, client: $this->client);
        /** @var AdminBookingsComponent $adminBookingsComponent */
        $adminBookingsComponent = $component->component();
        $bookings = $adminBookingsComponent->getAllBookings();
        $bookingIds = array_map(static fn(array $row) => (string) $row['booking']->getId(), $bookings);

        self::assertContains((string) $ownBooking->getId(), $bookingIds);
        self::assertNotContains((string) $otherBooking->getId(), $bookingIds);
    }
}
