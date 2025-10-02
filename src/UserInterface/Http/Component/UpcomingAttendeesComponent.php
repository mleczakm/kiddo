<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Repository\LessonRepository;
use App\Repository\UserRepository;
use App\Repository\BookingRepository;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class UpcomingAttendeesComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $week;

    #[LiveProp(writable: true, url: true)]
    public bool $showCancelled = false;

    // Fast booking modal state/fields
    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp(writable: true)]
    public ?string $selectedLessonId = null;

    // Autocomplete for existing users
    #[LiveProp(writable: true)]
    public ?string $userSearch = null;

    #[LiveProp(writable: true)]
    public ?string $selectedUserId = null;

    #[LiveProp(writable: true)]
    public string $customerEmail = '';

    #[LiveProp(writable: true)]
    public string $customerName = '';

    #[LiveProp(writable: true)]
    public string $notes = '';

    #[LiveProp]
    public ?string $errorMessage = null;

    #[LiveProp]
    public ?string $successMessage = null;

    // Actions dropdown state
    #[LiveProp(writable: true)]
    public ?string $actionsForBookingId = null;

    // Payment modal for booking payments
    #[LiveProp(writable: true)]
    public bool $paymentModalOpened = false;

    #[LiveProp]
    public ?string $paymentCode = null;

    public ?Money $paymentAmount = null;

    // Admin actions modal state
    #[LiveProp(writable: true)]
    public bool $adminModalOpened = false;

    #[LiveProp(writable: true)]
    public ?string $adminModalBookingId = null;

    #[LiveProp(writable: true)]
    public ?string $adminModalLessonId = null;

    #[LiveProp(writable: true)]
    public ?string $adminNote = null;

    #[LiveProp(writable: true)]
    public ?string $adminAction = null;

    #[LiveProp(writable: true)]
    public ?string $rescheduleDate = null;

    #[LiveProp(writable: true)]
    public ?string $rescheduleTime = null;

    public function __construct(
        private readonly LessonRepository $lessonRepository,
        private readonly UserRepository $userRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->week = Clock::get()->now()->format('Y-m-d');
        $this->adminAction = '';
    }

    /**
     * @return Lesson[]
     */
    public function getLessons(): array
    {
        $startDate = new \DateTimeImmutable($this->week);
        $endDate = $startDate->modify('+7 days');

        $lessons = $this->lessonRepository->findUpcomingWithBookingsInRange($startDate, $endDate, $this->showCancelled);

        return $lessons;
    }

    /**
     * @return array{carnets: Booking[], single: Booking[]}
     */
    public function getGroupedBookings(Lesson $lesson): array
    {
        $carnets = [];
        $single = [];

        foreach ($lesson->getBookings() as $booking) {
            // Skip cancelled bookings if not showing them
            if (! $booking->isActive() && ! $this->showCancelled) {
                continue;
            }

            if ($booking->isCarnet()) {
                $carnets[] = $booking;
            } else {
                $single[] = $booking;
            }
        }

        return [
            'carnets' => $carnets,
            'single' => $single,
        ];
    }

    public function getWeekEnd(): \DateTimeImmutable
    {
        return $this->getWeekStart()
            ->modify('+7 days');
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->week);
    }

    #[LiveAction]
    public function increaseCapacity(#[LiveArg] string $lessonId): void
    {
        // Convert incoming ULID string to Ulid object to ensure proper DBAL binding
        $id = Ulid::fromString($lessonId);
        $lesson = $this->lessonRepository->find($id);
        if ($lesson) {
            $lesson->getMetadata()
                ->capacity++;
            $this->entityManager->flush();
        }
    }

    #[LiveAction]
    public function decreaseCapacity(#[LiveArg] string $lessonId): void
    {
        $id = Ulid::fromString($lessonId);
        $lesson = $this->lessonRepository->find($id);
        if ($lesson && $lesson->getAvailableSpots() > 0) {
            $lesson->getMetadata()
                ->capacity--;
            $this->entityManager->flush();
        }
    }

    #[LiveAction]
    public function toggleCancelled(): void
    {
        $this->showCancelled = ! $this->showCancelled;
    }

    #[LiveAction]
    public function openActions(#[LiveArg] string $bookingId): void
    {
        // Repurpose to open admin modal per requirements
        $this->actionsForBookingId = null;
        $this->adminModalBookingId = $bookingId;
        $this->adminModalOpened = true;

        $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
        if ($booking instanceof Booking) {
            $this->adminNote = (string) ($booking->getNotes() ?? '');
        } else {
            $this->adminNote = null;
        }
    }

    #[LiveAction]
    public function closeActions(): void
    {
        $this->actionsForBookingId = null;
    }

    #[LiveAction]
    public function openAdminModal(#[LiveArg] string $bookingId, #[LiveArg] string $lessonId): void
    {
        $this->adminModalBookingId = $bookingId;
        $this->adminModalLessonId = $lessonId;
        $this->adminModalOpened = true;

        // Preload existing note if any
        $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
        if ($booking instanceof Booking) {
            $this->adminNote = (string) ($booking->getNotes() ?? '');
        } else {
            $this->adminNote = null;
        }
    }

    #[LiveAction]
    public function closeAdminModal(): void
    {
        $this->adminModalOpened = false;
        $this->adminModalBookingId = null;
        $this->adminModalLessonId = null;
        $this->adminNote = null;
        $this->adminAction = null;
        $this->rescheduleDate = null;
        $this->rescheduleTime = null;
    }

    #[LiveAction]
    public function openAdminActions(string $bookingId, string $lessonId): void
    {
        $this->adminModalOpened = true;
        $this->adminModalBookingId = $bookingId;
        $this->adminModalLessonId = $lessonId;
        $this->adminAction = null;
        $this->adminNote = null;
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    #[LiveAction]
    public function setAdminAction(string $action): void
    {
        $this->adminAction = $action;
        $this->errorMessage = null;
        $this->successMessage = null;

        if ($action === 'reschedule' && ! $this->rescheduleDate) {
            $this->rescheduleDate = new \DateTime()
                ->format('Y-m-d');
            $this->rescheduleTime = '18:00';
        }
    }

    #[LiveAction]
    public function executeAdminAction(): void
    {
        try {
            $booking = $this->bookingRepository->find($this->adminModalBookingId);
            $currentLesson = $this->lessonRepository->find($this->adminModalLessonId);

            if (! $booking || ! $currentLesson) {
                throw new \RuntimeException('Booking or lesson not found');
            }

            switch ($this->adminAction) {
                case 'add_note':
                    $booking->setNotes($this->adminNote);
                    $this->entityManager->flush();
                    $this->successMessage = 'Note added successfully';
                    break;

                case 'cancel':
                    $booking->cancelLesson($currentLesson->getId()->toString());

                    $this->entityManager->flush();
                    $this->successMessage = 'Booking cancelled successfully';
                    break;

                case 'mark_refund':
                    if ($booking->getPayment()) {
                        $booking->getPayment()
                            ->setStatus('refund_requested');
                        $this->entityManager->flush();
                    } else {
                        throw new \RuntimeException('No payment found for this booking');
                    }
                    break;

                case 'reschedule':
                    if (! $this->rescheduleDate || ! $this->rescheduleTime) {
                        throw new \RuntimeException('Please select a date and time');
                    }

                    $newDateTime = \DateTime::createFromFormat(
                        'Y-m-d H:i',
                        $this->rescheduleDate . ' ' . $this->rescheduleTime
                    );

                    throw new \RuntimeException('Invalid date or time format');



                default:
                    throw new \RuntimeException('Invalid action');
            }
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    #[LiveAction]
    public function saveBookingNote(#[LiveArg] string $bookingId): void
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
        if (! $booking instanceof Booking) {
            return;
        }
        $booking->setNotes($this->adminNote);
        $this->entityManager->flush();
    }

    #[LiveAction]
    public function markToRefund(#[LiveArg] string $bookingId, #[LiveArg] string $lessonId): void
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
        if (! $booking instanceof Booking) {
            return;
        }
        $booking->refundLesson($lessonId, 'Marked to refund by admin');
        $this->entityManager->flush();
    }

    #[LiveAction]
    public function openFastBooking(#[LiveArg] string $lessonId): void
    {
        $this->selectedLessonId = $lessonId;
        $this->modalOpened = true;
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
        $this->selectedLessonId = null;
        $this->selectedUserId = null;
        $this->userSearch = null;
        $this->customerEmail = '';
        $this->customerName = '';
        $this->notes = '';
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    /**
     * @return array<int, User>
     */
    public function getFilteredUsers(): array
    {
        if ($this->userSearch === null || strlen(trim($this->userSearch)) < 2) {
            return [];
        }
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.name LIKE :q OR u.email LIKE :q')
            ->setParameter('q', '%' . trim($this->userSearch) . '%')
            ->orderBy('u.name', 'ASC')
            ->setMaxResults(10);
        /** @var list<User> $result */
        $result = $qb->getQuery()
            ->getResult();
        return $result;
    }

    #[LiveAction]
    public function selectExistingUser(#[LiveArg] int $userId): void
    {
        $user = $this->userRepository->find($userId);

        if (! $user instanceof User) {
            return;
        }
        $this->selectedUserId = (string) $user->getId();

        $this->customerEmail = $user->getEmail();
        $this->customerName = $user->getName();
        $this->userSearch = null;
        $this->errorMessage = null;
    }

    #[LiveAction]
    public function clearSelectedUser(): void
    {
        $this->selectedUserId = null;
        $this->userSearch = null;
        $this->customerEmail = '';
        $this->customerName = '';
    }

    #[LiveAction]
    public function confirmFastBooking(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! $this->selectedLessonId) {
            $this->errorMessage = 'Brak wybranych zajęć';
            return;
        }
        if ($this->customerEmail === '') {
            $this->errorMessage = 'Podaj e-mail uczestnika';
            return;
        }
        if ($this->customerName === '') {
            $this->errorMessage = 'Podaj imię i nazwisko uczestnika';
            return;
        }

        try {
            $lesson = $this->lessonRepository->find(Ulid::fromString($this->selectedLessonId));
        } catch (\Throwable) {
            $lesson = null;
        }
        if (! $lesson) {
            $this->errorMessage = 'Nie znaleziono zajęć';
            return;
        }
        if (! $lesson->canBeBooked()) {
            $this->errorMessage = 'Brak miejsc lub zajęcia nieaktywne';
            return;
        }

        // Find or create user by email
        $user = $this->userRepository->findOneBy([
            'email' => $this->customerEmail,
        ]);
        if (! $user instanceof User) {
            $user = new User($this->customerEmail, $this->customerName);
            $this->entityManager->persist($user);
        } else {
            // ensure name present/updated for convenience
            if ($user->getName() !== $this->customerName) {
                $user->setName($this->customerName);
            }
        }

        // Create booking without payment and mark active immediately
        $booking = new Booking($user, null);
        $booking->addLesson($lesson);
        if ($this->notes !== '') {
            $booking->setNotes($this->notes);
        }
        $booking->setStatus(Booking::STATUS_ACTIVE);

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->successMessage = 'Dodano rezerwację';
        // Reset form but keep modal open to show success
        $this->notes = '';
    }

    #[LiveAction]
    public function markPaid(#[LiveArg] string $bookingId): void
    {
        try {
            $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
            if (! $booking) {
                return;
            }
            if (! $booking->payment) {
                // Create zero-amount payment to mark as paid if none exists
                $payment = new Payment($booking->getUser(), Money::of(0, 'PLN'));
                $this->entityManager->persist($payment);
                $booking->payment = $payment;
            }
            $booking->payment->setStatus(Payment::STATUS_PAID);
            $this->entityManager->flush();
        } catch (\Throwable) {
            // ignore
        }
    }

    #[LiveAction]
    public function markUnpaid(#[LiveArg] string $bookingId): void
    {
        try {
            $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
            if (! $booking || ! $booking->payment) {
                return;
            }
            $booking->payment->setStatus(Payment::STATUS_PENDING);
            $this->entityManager->flush();
        } catch (\Throwable) {
            // ignore
        }
    }

    #[LiveAction]
    public function payForBooking(#[LiveArg] string $bookingId, #[LiveArg] string $lessonId): void
    {
        $this->paymentCode = null;
        $this->paymentAmount = null;
        try {
            $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
            $lesson = $this->lessonRepository->find(Ulid::fromString($lessonId));
        } catch (\Throwable) {
            $booking = null;
            $lesson = null;
        }
        if (! $booking || ! $lesson) {
            return;
        }

        // Determine amount from lesson default ticket option
        $amount = $lesson->defaultTicketOption()
            ->price ?? Money::of(0, 'PLN');

        // Ensure booking has a Payment
        $payment = $booking->payment;
        if (! $payment) {
            $payment = new Payment($booking->getUser(), $amount);
            $this->entityManager->persist($payment);
            $booking->payment = $payment;
        }

        // If amount differs and current payment has zero/default, update it
        // (keep minimal logic to avoid complex reconciliation)
        if ((string) $payment->getAmount()->getAmount() !== (string) $amount->getAmount()) {
            // Replace by creating new payment to not mutate past ones
            $payment = new Payment($booking->getUser(), $amount);
            $this->entityManager->persist($payment);
            $booking->payment = $payment;
        }

        // Ensure PaymentCode exists
        if (! $payment->getPaymentCode()) {
            $code = new PaymentCode($payment);
            $this->entityManager->persist($code);
        }

        $this->entityManager->flush();

        $this->paymentCode = $booking->payment?->getPaymentCode()?->getCode();
        $this->paymentAmount = $booking->payment?->getAmount();
        $this->paymentModalOpened = true;
        $this->actionsForBookingId = null;
    }

    #[LiveAction]
    public function closePaymentModal(): void
    {
        $this->paymentModalOpened = false;
        $this->paymentCode = null;
        $this->paymentAmount = null;
    }
}
