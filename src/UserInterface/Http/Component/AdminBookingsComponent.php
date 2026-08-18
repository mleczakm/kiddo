<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\MoneyInputParser;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\User;
use App\Message\CancelLessonBooking;
use App\Message\ReactivateBooking;
use App\Message\RefundLessonBooking;
use App\Message\RescheduleLessonBooking;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use App\Repository\UserRepository;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

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
    public ?string $amount = null;

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

    // Reschedule picker state
    #[LiveProp(writable: true)]
    public ?string $reschedulingBookingId = null;

    #[LiveProp(writable: true)]
    public ?string $reschedulingLessonId = null;

    #[LiveProp(writable: true)]
    public ?string $newLessonId = null;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly UserRepository $userRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
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
        $qb = $this->bookingRepository
            ->createQueryBuilder('b')
            ->select('b', 'u', 'l', 'p', 's')
            ->leftJoin('b.user', 'u')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('b.payment', 'p')
            ->leftJoin('l.series', 's')
            ->leftJoin('l.metadata', 'm');

        // ROLE_HOST (without ROLE_ADMIN) only ever sees bookings for lessons
        // they're assigned to as an instructor — directly, or via the
        // lesson's series (mirrors Lesson::getAllInstructors() semantics).
        if (!$this->isGranted('ROLE_ADMIN')) {
            $host = $this->getUser();
            if (!$host instanceof User) {
                return [];
            }

            $qb->andWhere(':host MEMBER OF l.instructors OR :host MEMBER OF s.instructors')->setParameter(
                'host',
                $host,
            );
        }

        // Apply status filter
        if ($this->filter === 'active') {
            $qb->andWhere('b.status IN (:statuses)')->setParameter('statuses', [
                Booking::STATUS_PENDING,
                Booking::STATUS_ACTIVE,
            ]);
        } elseif ($this->filter === 'completed') {
            $qb->andWhere('b.status = :status')->setParameter('status', Booking::STATUS_PAST);
        } elseif ($this->filter === 'cancelled') {
            $qb->andWhere('b.status = :status')->setParameter('status', Booking::STATUS_CANCELLED);
        } else {
            $qb->andWhere('b.status IN (:statuses)')->setParameter('statuses', [
                Booking::STATUS_PENDING,
                Booking::STATUS_ACTIVE,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_PAST,
            ]);
        }

        // Apply search filter
        if ($this->search) {
            $qb->andWhere('u.name LIKE :search OR u.email LIKE :search OR m.title LIKE :search')->setParameter(
                'search',
                '%' . $this->search . '%',
            );
        }

        $qb->orderBy('b.createdAt', 'DESC')->setMaxResults(50);

        /** @var Booking[] $bookings */
        $bookings = $qb->getQuery()->getResult();

        $result = [];
        foreach ($bookings as $booking) {
            $isCarnet = $this->isCarnetBooking($booking);

            // Use new BookedLesson structure
            $totalLessons = count($booking->getLessons());
            $completedLessons = 0;
            $upcomingLessons = [];

            // Calculate completed and upcoming lessons from actual lessons
            foreach ($booking->getLessons() as $lesson) {
                if ($lesson->schedule < Clock::get()->now()) {
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
        $counts = $this->bookingRepository
            ->createQueryBuilder('b')
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

            if ($status === Booking::STATUS_ACTIVE || $status === Booking::STATUS_PENDING) {
                $result['active'] += $countValue;
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
        return $this->lessonRepository
            ->createQueryBuilder('l')
            ->leftJoin('l.series', 's')
            ->where('l.status = :status')
            ->andWhere('l.schedule > :now')
            ->setParameter('status', 'active')
            ->setParameter('now', Clock::get()->now())
            ->orderBy('l.schedule', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    #[LiveAction]
    public function addManualBooking(): void
    {
        try {
            // Validate required fields
            if (!$this->customerName || !$this->customerEmail || !$this->amount || !$this->paymentMethod) {
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
            if (!$user) {
                $user = new User($this->customerEmail, $this->customerName);
                $this->entityManager->persist($user);
            }

            // Create payment with correct Money object
            $normalizedAmount = MoneyInputParser::parse($this->amount);
            if ($normalizedAmount === null) {
                $this->errorMessage = 'Imię, email, kwota i sposób płatności są wymagane';
                return;
            }
            $money = Money::of($normalizedAmount, 'PLN');
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
        } catch (\InvalidArgumentException) {
            $this->errorMessage = 'Podaj poprawną kwotę, np. 150,00';
        } catch (\Exception $e) {
            $this->errorMessage = 'Wystąpił błąd podczas dodawania rezerwacji: ' . $e->getMessage();
        }
    }

    #[LiveAction]
    public function markAsPaid(string $bookingId): void
    {
        try {
            $id = Ulid::fromString($bookingId);
            $booking = $this->bookingRepository->find($id);
            if (!$booking) {
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
                static fn($id) => $id !== $bookingId,
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
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            $this->errorMessage = 'Unable to cancel lesson: not logged in as admin';
            return;
        }

        try {
            $this->messageBus->dispatch(
                new CancelLessonBooking(
                    Ulid::fromString($bookingId),
                    Ulid::fromString($lessonId),
                    $admin,
                    'Cancelled by admin',
                ),
            );
            $this->successMessage = 'Lesson cancelled successfully';
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to cancel lesson: ' . $e->getMessage();
        }
    }

    #[LiveAction]
    public function reactivateBooking(#[LiveArg] string $bookingId): void
    {
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            $this->errorMessage = 'Nie udało się przywrócić rezerwacji: brak uprawnień administratora';
            return;
        }

        try {
            $this->messageBus->dispatch(
                new ReactivateBooking(Ulid::fromString($bookingId), $admin, 'Przywrócono przez administratora'),
            );
            $this->successMessage = 'Rezerwacja została przywrócona';
        } catch (\Exception $e) {
            $this->errorMessage = 'Nie udało się przywrócić rezerwacji: ' . $e->getMessage();
        }
    }

    #[LiveAction]
    public function refundLesson(#[LiveArg] string $bookingId, #[LiveArg] string $lessonId): void
    {
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            $this->errorMessage = 'Unable to refund lesson: not logged in as admin';
            return;
        }

        try {
            $this->messageBus->dispatch(
                new RefundLessonBooking(
                    Ulid::fromString($bookingId),
                    Ulid::fromString($lessonId),
                    $admin,
                    'Refunded by admin',
                ),
            );
            $this->successMessage = 'Lesson refunded successfully';
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to refund lesson: ' . $e->getMessage();
        }
    }

    #[LiveAction]
    public function startReschedule(#[LiveArg] string $bookingId, #[LiveArg] string $lessonId): void
    {
        $this->reschedulingBookingId = $bookingId;
        $this->reschedulingLessonId = $lessonId;
        $this->newLessonId = null;
    }

    #[LiveAction]
    public function cancelReschedule(): void
    {
        $this->reschedulingBookingId = null;
        $this->reschedulingLessonId = null;
        $this->newLessonId = null;
    }

    #[LiveAction]
    public function reschedule(): void
    {
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            $this->errorMessage = 'Unable to reschedule: not logged in as admin';
            return;
        }

        if (!$this->reschedulingBookingId || !$this->reschedulingLessonId || !$this->newLessonId) {
            $this->errorMessage = 'Wybierz nowy termin, aby przełożyć zajęcia';
            return;
        }

        try {
            $this->messageBus->dispatch(
                new RescheduleLessonBooking(
                    Ulid::fromString($this->reschedulingBookingId),
                    Ulid::fromString($this->reschedulingLessonId),
                    Ulid::fromString($this->newLessonId),
                    $admin,
                    'Rescheduled by admin',
                ),
            );
            $this->successMessage = 'Lesson rescheduled successfully';
            $this->cancelReschedule();
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to reschedule lesson: ' . $e->getMessage();
        }
    }

    public function isReschedulingLesson(string $bookingId, string $lessonId): bool
    {
        return $this->reschedulingBookingId === $bookingId && $this->reschedulingLessonId === $lessonId;
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
            $now = Clock::get()->now();
            $schedule = $lesson->schedule;

            if ($schedule < Clock::get()->now()) {
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
        $now = Clock::get()->now();
        $lessonMap = $booking->getLessonsMap();
        $lessonId = $lesson->getId();

        // Can modify only if lesson is in active map and in the future
        return $lesson->schedule > $now && $booking->canBeRescheduled() && $lessonMap->active()->hasKey($lessonId);
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
            if (!is_array($decoded)) {
                return [];
            }
            /** @var list<string> $ids */
            return array_values(array_filter($decoded, static fn($v) => is_string($v) && Ulid::isValid($v)));
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
        $ids = array_map(Ulid::fromString(...), $idStrings);

        /** @var Lesson[] $lessons */
        return $this->lessonRepository->findBy([
            'id' => $ids,
        ]);
    }

    /**
     * Get filtered lessons for autocomplete dropdown
     * @return Lesson[]
     */
    public function getFilteredLessons(): array
    {
        $qb = $this->lessonRepository
            ->createQueryBuilder('l')
            ->join('l.metadata', 'm')
            ->leftJoin('l.series', 's')
            ->where('l.status = :status')
            ->andWhere('l.schedule > :now')
            ->setParameter('status', 'active')
            ->setParameter('now', Clock::get()->now())
            ->orderBy('l.schedule', 'ASC')
            ->setMaxResults(10);

        // Apply search filter
        $searchTerm = '%' . $this->lessonSearch . '%';
        $qb->andWhere('m.title LIKE :search OR m.description LIKE :search')->setParameter('search', $searchTerm);

        /** @var Lesson[] $result */
        return $qb->getQuery()->getResult();
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
        $lessonIdString = $lessonId;
        $selectedIds = $this->getSelectedLessonIdsArray();

        if (!in_array($lessonIdString, $selectedIds, true)) {
            $selectedIds[] = $lessonIdString;
            $this->selectedLessonIds = (string) json_encode($selectedIds);
        }
    }

    #[LiveAction]
    public function removeLesson(#[LiveArg] string $lessonId): void
    {
        $lessonIdString = $lessonId;
        $selectedIds = $this->getSelectedLessonIdsArray();
        $selectedIds = array_values(array_filter($selectedIds, static fn($id) => $id !== $lessonIdString));
        $this->selectedLessonIds = (string) json_encode($selectedIds);
    }

    /**
     * Calculate amount per lesson for a booking
     */
    public function getAmountPerLesson(Booking $booking): ?Money
    {
        if (!$booking->getPayment() || count($booking->getLessons()) === 0) {
            return null;
        }

        $totalAmount = $booking->getPayment()->getAmount();
        $lessonCount = count($booking->getLessons());

        // Divide the money amount by lesson count
        return $totalAmount->dividedBy($lessonCount, RoundingMode::HALF_UP);
    }
}
