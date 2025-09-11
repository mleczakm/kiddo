<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Brick\Money\Money;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use App\Entity\Payment;
use App\Repository\BookingRepository;
use App\Repository\UserRepository;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Brick\Math\RoundingMode;

#[AsLiveComponent]
class AdminBookingsComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $filter = 'active'; // all, active, completed, cancelled

    #[LiveProp(writable: true)]
    public ?string $search = null;

    // Manual booking form properties
    #[LiveProp(writable: true)]
    public ?string $customerName = null;

    #[LiveProp(writable: true)]
    public ?string $customerEmail = null;

    #[LiveProp(writable: true)]
    public ?float $amount = null;

    #[LiveProp(writable: true)]
    public ?string $paymentMethod = null;

    #[LiveProp(writable: true)]
    public ?string $notes = null;

    // Lesson selection properties
    #[LiveProp(writable: true)]
    public string $selectedLessonIds = '[]';

    #[LiveProp(writable: true)]
    public ?string $lessonSearch = null;

    #[LiveProp(writable: true)]
    public ?string $successMessage = null;

    #[LiveProp(writable: true)]
    public ?string $errorMessage = null;

    /**
     * @var array<string>
     */
    #[LiveProp(writable: true)]
    public array $expandedBookings = [];

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly UserRepository $userRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * @return list<array{
     *     booking: Booking,
     *     isCarnet: bool,
     *     totalLessons: int,
     *     completedLessons: int,
     *     remainingLessons: int,
     *     progress: float,
     *     upcomingLessons: array<int, Lesson>
     * }>
     */
    public function getAllBookings(): array
    {
        $qb = $this->bookingRepository->createQueryBuilder('b')
            ->select('b', 'u', 'l', 'p', 's')
            ->leftJoin('b.user', 'u')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('b.payment', 'p')
            ->leftJoin('l.series', 's');

        // Apply status filter
        if ($this->filter === 'active') {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', Booking::STATUS_ACTIVE);
        } elseif ($this->filter === 'completed') {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', Booking::STATUS_PAST);
        } elseif ($this->filter === 'cancelled') {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', Booking::STATUS_CANCELLED);
        } else {
            $qb->andWhere('b.status IN (:statuses)')
                ->setParameter('statuses', [
                    Booking::STATUS_PENDING,
                    Booking::STATUS_ACTIVE,
                    Booking::STATUS_CANCELLED,
                    Booking::STATUS_PAST,
                ]);
        }

        // Apply search filter
        if ($this->search) {
            $qb->andWhere('u.name LIKE :search OR u.email LIKE :search OR l.metadata.title LIKE :search')
                ->setParameter('search', '%' . $this->search . '%');
        }

        $qb->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(50);

        /** @var Booking[] $bookings */
        $bookings = $qb->getQuery()
            ->getResult();

        $result = [];
        foreach ($bookings as $booking) {
            $isCarnet = $this->isCarnetBooking($booking);

            // Use new BookedLesson structure
            $totalLessons = count($booking->getLessons());
            $completedLessons = 0;
            $upcomingLessons = [];

            // Calculate completed and upcoming lessons from actual lessons
            foreach ($booking->getLessons() as $lesson) {
                if ($lesson->getMetadata()->schedule < new \DateTimeImmutable()) {
                    $completedLessons++;
                } else {
                    $upcomingLessons[] = $lesson;
                }
            }

            $result[] = [
                'booking' => $booking,
                'isCarnet' => $isCarnet,
                'totalLessons' => $totalLessons,
                'completedLessons' => $completedLessons,
                'remainingLessons' => count($upcomingLessons),
                'progress' => $totalLessons > 0 ? (float) ($completedLessons / $totalLessons) : 0.0,
                'upcomingLessons' => $upcomingLessons,
            ];
        }

        return $result;
    }

    private function isCarnetBooking(Booking $booking): bool
    {
        if ($booking->getLessonsMap()->count() > 1) {
            return true;
        }

        return false;
    }

    /**
     * @return array{all: int, active: int, completed: int, cancelled: int}
     */
    public function getFilterCounts(): array
    {
        /** @var list<array{status: string, count: string}> $counts */
        $counts = $this->bookingRepository->createQueryBuilder('b')
            ->select('b.status', 'COUNT(b.id) as count')
            ->groupBy('b.status')
            ->getQuery()
            ->getResult();

        $result = [
            'all' => 0,
            'active' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        foreach ($counts as $count) {
            $countValue = (int) $count['count'];
            $status = $count['status'];
            $result['all'] += $countValue;

            // Normalize legacy/alias statuses coming from queries/tests
            if ($status === 'confirmed') {
                $status = Booking::STATUS_ACTIVE;
            } elseif ($status === 'completed') {
                $status = Booking::STATUS_PAST;
            }

            if ($status === Booking::STATUS_ACTIVE) {
                $result['active'] = $countValue;
            } elseif ($status === Booking::STATUS_PAST) {
                $result['completed'] = $countValue;
            } elseif ($status === Booking::STATUS_CANCELLED) {
                $result['cancelled'] = $countValue;
            }
        }

        return $result;
    }

    /**
     * Get available lessons for booking selection
     * @return Lesson[]
     */
    public function getAvailableLessons(): array
    {
        /** @var Lesson[] $result */
        $result = $this->lessonRepository->createQueryBuilder('l')
            ->leftJoin('l.series', 's')
            ->where('l.status = :status')
            ->andWhere('l.metadata.schedule > :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('l.metadata.schedule', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
        return $result;
    }

    #[LiveAction]
    public function addManualBooking(): void
    {
        try {
            // Validate required fields
            if (! $this->customerName || ! $this->customerEmail || ! $this->amount || ! $this->paymentMethod) {
                $this->errorMessage = 'Imię, email, kwota i sposób płatności są wymagane';
                return;
            }

            // Get selected lesson IDs as array
            $selectedLessonIds = $this->getSelectedLessonIdsArray();

            // Validate lesson selection
            if (empty($selectedLessonIds)) {
                $this->errorMessage = 'Wybierz przynajmniej jedną lekcję';
                return;
            }

            // Find or create user
            $user = $this->userRepository->findOneBy([
                'email' => $this->customerEmail,
            ]);
            if (! $user) {
                $user = new User($this->customerEmail, $this->customerName);
                $this->entityManager->persist($user);
            }

            // Create payment with correct Money object
            $money = Money::of($this->amount, 'PLN');
            $payment = new Payment($user, $money);
            $payment->setStatus(Payment::STATUS_PAID);
            $this->entityManager->persist($payment);

            // Find lessons
            $lessons = $this->lessonRepository->findBy([
                'id' => $selectedLessonIds,
            ]);
            if (empty($lessons)) {
                $this->errorMessage = 'Nie znaleziono wybranych lekcji';
                return;
            }

            // Create booking with all lessons
            $booking = new Booking($user, $payment, ...$lessons);
            $booking->setStatus(Booking::STATUS_ACTIVE); // Manually created bookings are active immediately

            if ($this->notes) {
                $booking->setNotes($this->notes);
            }

            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            // Clear form and show success message
            $this->clearForm();
            $this->successMessage = 'Rezerwacja została pomyślnie dodana';
            $this->errorMessage = null;

        } catch (\Exception $e) {
            $this->errorMessage = 'Wystąpił błąd podczas dodawania rezerwacji: ' . $e->getMessage();
        }
    }

    #[LiveAction]
    public function markAsPaid(string $bookingId): void
    {
        try {
            $booking = $this->bookingRepository->find($bookingId);
            if (! $booking) {
                $this->errorMessage = 'Nie znaleziono rezerwacji';
                return;
            }

            if ($booking->payment) {
                $booking->payment->setStatus(Payment::STATUS_PAID);
                $this->entityManager->flush();
                $this->successMessage = 'Płatność została oznaczona jako opłacona';
            }
        } catch (\Exception) {
            $this->errorMessage = 'Wystąpił błąd podczas aktualizacji płatności';
        }
    }

    #[LiveAction]
    public function toggleBookingExpansion(#[LiveArg] string $bookingId): void
    {
        if (in_array($bookingId, $this->expandedBookings, true)) {
            $this->expandedBookings = array_values(array_filter(
                $this->expandedBookings,
                fn($id) => $id !== $bookingId
            ));
        } else {
            $this->expandedBookings[] = $bookingId;
        }
    }

    public function isBookingExpanded(string $bookingId): bool
    {
        return in_array($bookingId, $this->expandedBookings, true);
    }

    #[LiveAction]
    public function cancelLesson(#[LiveArg] string $bookingId, #[LiveArg] string $lessonId): void
    {
        try {
            $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));

            if (! $booking) {
                $this->errorMessage = 'Booking not found';
                return;
            }

            if ($booking->cancelLesson($lessonId, 'Cancelled by admin')) {
                // If no active lessons remain, mark booking as cancelled
                if (! $booking->hasActiveBookedLessons()) {
                    $booking->cancel(null, 'All lessons cancelled');
                }

                $this->entityManager->flush();
                $this->successMessage = 'Lesson cancelled successfully';
            } else {
                $this->errorMessage = 'Unable to cancel lesson - it may already be cancelled or not found';
            }

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to cancel lesson: ' . $e->getMessage();
        }
    }

    #[LiveAction]
    public function refundLesson(#[LiveArg] string $bookingId, #[LiveArg] string $lessonId): void
    {
        try {
            $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));

            if (! $booking) {
                $this->errorMessage = 'Booking not found';
                return;
            }

            if ($booking->refundLesson($lessonId, 'Refunded by admin')) {
                $this->entityManager->flush();
                $this->successMessage = 'Lesson refunded successfully';
            } else {
                $this->errorMessage = 'Unable to refund lesson - it may already be processed or not found';
            }

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to refund lesson: ' . $e->getMessage();
        }
    }

    /**
     * @return array{class: string, text: string}
     */
    public function getLessonStatusBadge(Lesson $lesson, Booking $booking): array
    {
        // Check if lesson exists in booking's lesson map
        $lessonMap = $booking->getLessonsMap();
        $lessonId = $lesson->getId();

        // Check if lesson is in cancelled map
        if ($lessonMap->cancelled()->hasKey($lessonId)) {
            return [
                'class' => 'bg-red-500 text-white',
                'text' => 'Cancelled',
            ];
        }

        // Check if lesson is in past map
        if ($lessonMap->past()->hasKey($lessonId)) {
            return [
                'class' => 'bg-green-500 text-white',
                'text' => 'Completed',
            ];
        }

        // Check if lesson is in active map
        if ($lessonMap->active()->hasKey($lessonId)) {
            $now = new \DateTimeImmutable();
            $schedule = $lesson->getMetadata()
                ->schedule;

            if ($schedule < $now) {
                return [
                    'class' => 'bg-green-500 text-white',
                    'text' => 'Completed',
                ];
            }

            return [
                'class' => 'bg-blue-500 text-white',
                'text' => 'Scheduled',
            ];
        }

        return [
            'class' => 'bg-gray-500 text-white',
            'text' => 'Unknown',
        ];
    }

    public function canLessonBeModified(Lesson $lesson, Booking $booking): bool
    {
        $now = new \DateTimeImmutable();
        $lessonMap = $booking->getLessonsMap();
        $lessonId = $lesson->getId();

        // Can modify only if lesson is in active map and in the future
        return $lesson->getMetadata()
            ->schedule > $now
                        && $booking->canBeRescheduled()
            && $lessonMap->active()
                ->hasKey($lessonId);
    }

    #[LiveAction]
    public function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    private function clearForm(): void
    {
        $this->customerName = null;
        $this->customerEmail = null;
        $this->amount = null;
        $this->paymentMethod = null;
        $this->notes = null;
        $this->selectedLessonIds = '[]';
        $this->lessonSearch = null;
    }

    /**
     * Get selected lesson IDs as array of ULID strings
     * @return list<string>
     */
    public function getSelectedLessonIdsArray(): array
    {
        try {
            $decoded = json_decode($this->selectedLessonIds, true);
            if (! is_array($decoded)) {
                return [];
            }
            /** @var list<string> $ids */
            $ids = array_values(array_filter($decoded, static fn($v) => is_string($v) && Ulid::isValid($v)));
            return $ids;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Get selected lessons data
     * @return Lesson[]
     */
    public function getSelectedLessons(): array
    {
        $idStrings = $this->getSelectedLessonIdsArray();
        if ($idStrings === []) {
            return [];
        }
        $ids = array_map(static fn(string $s) => Ulid::fromString($s), $idStrings);

        /** @var Lesson[] $lessons */
        $lessons = $this->lessonRepository->findBy([
            'id' => $ids,
        ]);
        return $lessons;
    }

    /**
     * Get filtered lessons for autocomplete dropdown
     * @return Lesson[]
     */
    public function getFilteredLessons(): array
    {
        $qb = $this->lessonRepository->createQueryBuilder('l')
            ->leftJoin('l.series', 's')
            ->where('l.status = :status')
            ->andWhere('l.metadata.schedule > :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('l.metadata.schedule', 'ASC')
            ->setMaxResults(10);

        // Apply search filter
        $searchTerm = '%' . $this->lessonSearch . '%';
        $qb->andWhere('l.metadata.title LIKE :search OR l.metadata.description LIKE :search')
            ->setParameter('search', $searchTerm);

        /** @var Lesson[] $result */
        $result = $qb->getQuery()
            ->getResult();
        return $result;
    }

    /**
     * Check if a lesson is currently selected
     */
    public function isLessonSelected(string $lessonId): bool
    {
        return in_array($lessonId, $this->getSelectedLessonIdsArray(), true);
    }

    #[LiveAction]
    public function selectLesson(#[LiveArg] string $lessonId): void
    {
        $lessonIdString = (string) $lessonId;
        $selectedIds = $this->getSelectedLessonIdsArray();

        if (! in_array($lessonIdString, $selectedIds, true)) {
            $selectedIds[] = $lessonIdString;
            $this->selectedLessonIds = (string) json_encode($selectedIds);
        }
    }

    #[LiveAction]
    public function removeLesson(#[LiveArg] string $lessonId): void
    {
        $lessonIdString = (string) $lessonId;
        $selectedIds = $this->getSelectedLessonIdsArray();
        $selectedIds = array_values(array_filter($selectedIds, fn($id) => $id !== $lessonIdString));
        $this->selectedLessonIds = (string) json_encode($selectedIds);
    }

    /**
     * Calculate amount per lesson for a booking
     */
    public function getAmountPerLesson(Booking $booking): ?Money
    {
        if (! $booking->getPayment() || count($booking->getLessons()) === 0) {
            return null;
        }

        $totalAmount = $booking->getPayment()
            ->getAmount();
        $lessonCount = count($booking->getLessons());

        // Divide the money amount by lesson count
        return $totalAmount->dividedBy($lessonCount, RoundingMode::HALF_UP);
    }
}
