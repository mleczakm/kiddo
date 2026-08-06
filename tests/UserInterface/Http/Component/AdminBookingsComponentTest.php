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

        self::assertTrue($component->component()->isReschedulingLesson((string) $booking->getId(), (string) $oldLesson->getId()));

        $component->set('newLessonId', (string) $newLesson->getId());
        $component->call('reschedule');

        $this->transport('async')->queue()->assertContains(RescheduleLessonBooking::class, 1);

        // Picker state is cleared after a successful dispatch
        self::assertFalse($component->component()->isReschedulingLesson((string) $booking->getId(), (string) $oldLesson->getId()));
    }
}
