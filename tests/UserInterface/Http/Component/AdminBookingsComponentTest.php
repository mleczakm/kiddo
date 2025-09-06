<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\AgeRange;
use App\Entity\User;
use App\Entity\Payment;
use App\Repository\BookingRepository;
use App\Repository\UserRepository;
use App\Repository\LessonRepository;
use App\UserInterface\Http\Component\AdminBookingsComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminBookingsComponentTest extends TestCase
{
    private BookingRepository&MockObject $bookingRepository;

    private UserRepository&MockObject $userRepository;

    private LessonRepository&MockObject $lessonRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private AdminBookingsComponent $component;

    protected function setUp(): void
    {
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->lessonRepository = $this->createMock(LessonRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->component = new AdminBookingsComponent(
            $this->bookingRepository,
            $this->userRepository,
            $this->lessonRepository,
            $this->entityManager
        );
    }

    public function testDefaultFilterIsAll(): void
    {
        $this->assertEquals('all', $this->component->filter);
    }

    public function testGetAvailableLessonsReturnsUpcomingLessons(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $lesson1 = $this->createLesson('Workshop 1', new \DateTimeImmutable('+1 day'));
        $lesson2 = $this->createLesson('Workshop 2', new \DateTimeImmutable('+2 days'));

        $this->lessonRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('l')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('leftJoin')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$lesson1, $lesson2]);

        $result = $this->component->getAvailableLessons();

        $this->assertCount(2, $result);
        $this->assertContains($lesson1, $result);
        $this->assertContains($lesson2, $result);
    }

    public function testAddManualBookingValidatesRequiredFields(): void
    {
        // Test with missing required fields
        $this->component->customerName = null;
        $this->component->customerEmail = 'test@example.com';
        $this->component->amount = 100.0;
        $this->component->paymentMethod = 'cash';

        $this->component->addManualBooking();

        $this->assertEquals('Imię, email, kwota i sposób płatności są wymagane', $this->component->errorMessage);
    }

    public function testAddManualBookingValidatesLessonSelection(): void
    {
        // Test with no lessons selected
        $this->component->customerName = 'Test User';
        $this->component->customerEmail = 'test@example.com';
        $this->component->amount = 100.0;
        $this->component->paymentMethod = 'cash';
        $this->component->selectedLessonIds = [];

        $this->component->addManualBooking();

        $this->assertEquals('Wybierz przynajmniej jedną lekcję', $this->component->errorMessage);
    }

    public function testAddManualBookingCreatesNewUserWhenNotExists(): void
    {
        $this->setupSuccessfulBookingTest();

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'email' => 'test@example.com',
            ])
            ->willReturn(null);

        $this->entityManager->expects($this->exactly(3)) // user, payment, booking
            ->method('persist');

        $this->component->addManualBooking();

        $this->assertEquals('Rezerwacja została pomyślnie dodana', $this->component->successMessage);
        $this->assertNull($this->component->errorMessage);
    }

    public function testAddManualBookingUsesExistingUser(): void
    {
        $this->setupSuccessfulBookingTest();

        $existingUser = new User('test@example.com', 'Existing User');

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'email' => 'test@example.com',
            ])
            ->willReturn($existingUser);

        $this->entityManager->expects($this->exactly(2)) // payment, booking (no user persist)
            ->method('persist');

        $this->component->addManualBooking();

        $this->assertEquals('Rezerwacja została pomyślnie dodana', $this->component->successMessage);
    }

    public function testMarkAsPaidUpdatesPaymentStatus(): void
    {
        $bookingId = 'test-booking-id';
        $user = new User('test@example.com', 'Test User');
        $payment = new Payment($user, Money::of(100, 'PLN'));
        $lesson = $this->createLesson('Test Lesson', new \DateTimeImmutable());
        $booking = new Booking($user, $payment, $lesson);

        $this->bookingRepository->expects($this->once())
            ->method('find')
            ->with($bookingId)
            ->willReturn($booking);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->component->markAsPaid($bookingId);

        $this->assertEquals('Płatność została oznaczona jako opłacona', $this->component->successMessage);
        $this->assertEquals(Payment::STATUS_PAID, $payment->getStatus());
    }

    public function testMarkAsPaidHandlesBookingNotFound(): void
    {
        $bookingId = 'non-existent-booking';

        $this->bookingRepository->expects($this->once())
            ->method('find')
            ->with($bookingId)
            ->willReturn(null);

        $this->component->markAsPaid($bookingId);

        $this->assertEquals('Nie znaleziono rezerwacji', $this->component->errorMessage);
    }

    public function testClearMessagesResetsMessages(): void
    {
        $this->component->successMessage = 'Success';
        $this->component->errorMessage = 'Error';

        $this->component->clearMessages();

        $this->assertNull($this->component->successMessage);
        $this->assertNull($this->component->errorMessage);
    }

    public function testGetFilterCountsReturnsCorrectCounts(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->bookingRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('b')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('select')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('groupBy')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([
                [
                    'status' => 'confirmed',
                    'count' => '5',
                ],
                [
                    'status' => 'completed',
                    'count' => '3',
                ],
                [
                    'status' => 'cancelled',
                    'count' => '2',
                ],
            ]);

        $result = $this->component->getFilterCounts();

        $expected = [
            'all' => 10,
            'active' => 5,
            'completed' => 3,
            'cancelled' => 2,
        ];

        $this->assertEquals($expected, $result);
    }

    private function setupSuccessfulBookingTest(): void
    {
        $this->component->customerName = 'Test User';
        $this->component->customerEmail = 'test@example.com';
        $this->component->amount = 100.0;
        $this->component->paymentMethod = 'cash';
        $this->component->selectedLessonIds = ['lesson-id-1'];

        $lesson = $this->createLesson('Test Lesson', new \DateTimeImmutable());

        $this->lessonRepository->expects($this->once())
            ->method('findBy')
            ->with([
                'id' => ['lesson-id-1'],
            ])
            ->willReturn([$lesson]);

        $this->entityManager->expects($this->once())
            ->method('flush');
    }

    private function createLesson(string $title, \DateTimeImmutable $schedule): Lesson
    {
        $ageRange = new AgeRange(0, 18);
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test description',
            capacity: 10,
            schedule: $schedule,
            duration: 60,
            ageRange: $ageRange,
            category: 'test'
        );

        return new Lesson($metadata, []);
    }
}
