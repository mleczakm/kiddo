<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Repository\LessonRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\UpcomingAttendeesComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class UpcomingAttendeesComponentFunctionalTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $entityManager;

    private LessonRepository $lessonRepository;

    private KernelBrowser $client;

    public function testCanRenderWithUpcomingLessons(): void
    {

        // Create test data
        $futureDate = new \DateTimeImmutable('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($futureDate)->withCapacity(5)->assemble())
            ->assemble();

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // Create and test the component
        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);
        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString($lesson->getMetadata()->title, $rendered);
        $this->assertStringContainsString('5', $rendered); // Check if capacity is displayed
    }

    public function testIncreaseCapacity(): void
    {
        // Create test data
        $futureDate = new \DateTimeImmutable('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($futureDate)->withCapacity(5)->assemble())
            ->assemble();

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // Create and test the component
        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);

        // Test increasing capacity
        $testComponent->call('increaseCapacity', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        // Verify the capacity was increased
        $updatedLesson = $this->lessonRepository->find($lesson->getId()) ?? throw new \LogicException(
            'Lesson not found'
        );
        $this->assertEquals(6, $updatedLesson->getMetadata()->capacity);
    }

    public function testDecreaseCapacity(): void
    {
        $futureDate = new \DateTimeImmutable('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($futureDate)->withCapacity(1)->assemble())
            ->assemble();

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // Create and test the component
        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);

        // Test decreasing capacity
        $testComponent->call('decreaseCapacity', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        // Verify the capacity was decreased
        $updatedLesson = $this->lessonRepository->find($lesson->getId()) ?? throw new \LogicException(
            'Lesson not found'
        );
        $this->assertEquals(0, $updatedLesson->getMetadata()->capacity);
    }

    public function testCannotDecreaseCapacityBelowBookings(): void
    {
        // Create test data with 3 bookings (out of 3 capacity)
        $futureDate = new \DateTimeImmutable('+1 day');

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($futureDate)->withCapacity(3)->assemble())
            ->assemble();

        $user = UserAssembler::new()->assemble();

        $booking1 = BookingAssembler::new()
            ->withStatus('active')
            ->withUser($user)
            ->withLessons($lesson)
            ->assemble();
        $booking2 = BookingAssembler::new()
            ->withStatus('active')
            ->withLessons($lesson)
            ->withUser($user)
            ->assemble();
        $booking3 = BookingAssembler::new()
            ->withStatus('active')
            ->withUser($user)
            ->withLessons($lesson)
            ->assemble();

        $lesson->addBooking($booking1);
        $lesson->addBooking($booking2);
        $lesson->addBooking($booking3);

        $this->entityManager->persist($user);
        $this->entityManager->persist($booking1);
        $this->entityManager->persist($booking2);
        $this->entityManager->persist($booking3);


        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        // Create and test the component
        $testComponent = $this->createLiveComponent(name: UpcomingAttendeesComponent::class, client: $this->client);

        // Test decreasing capacity (should not work)
        $testComponent->call('decreaseCapacity', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        // Verify the capacity was NOT decreased (still 3)
        $updatedLesson = $this->lessonRepository->find($lesson->getId()) ?? throw new \LogicException(
            'Lesson not found'
        );
        $this->assertEquals(3, $updatedLesson->getMetadata()->capacity);
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->lessonRepository = self::getContainer()->get(LessonRepository::class);
    }
}
