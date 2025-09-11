<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use App\Entity\Payment;
use App\Repository\UserRepository;
use App\Repository\LessonRepository;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class AdminBookingModal extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp(writable: true)]
    public string $selectedLessonIds = '[]';

    #[LiveProp(writable: true)]
    public ?string $lessonSearch = null;

    #[LiveProp(writable: true)]
    public ?string $selectedUserId = null;

    #[LiveProp(writable: true)]
    public ?string $userSearch = null;

    #[LiveProp(writable: true)]
    public string $bookingType = 'single'; // single, carnet

    #[LiveProp(writable: true)]
    public ?float $amount = null;

    #[LiveProp(writable: true)]
    public string $paymentMethod = 'cash';

    #[LiveProp(writable: true)]
    public ?string $notes = null;

    #[LiveProp(writable: true)]
    public ?string $successMessage = null;

    #[LiveProp(writable: true)]
    public ?string $errorMessage = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
        $this->resetForm();
    }

    /**
     * Get filtered lessons for autocomplete dropdown
     * @return Lesson[]
     */
    public function getFilteredLessons(): array
    {
        if (empty($this->lessonSearch) || strlen($this->lessonSearch) < 2) {
            return [];
        }

        $qb = $this->lessonRepository->createQueryBuilder('l')
            ->leftJoin('l.series', 's')
            ->where('l.status = :status')
            ->andWhere('l.metadata.schedule > :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('l.metadata.schedule', 'ASC')
            ->setMaxResults(10);

        $searchTerm = '%' . $this->lessonSearch . '%';
        $qb->andWhere('l.metadata.title LIKE :search OR l.metadata.description LIKE :search OR s.name LIKE :search')
            ->setParameter('search', $searchTerm);

        /** @var Lesson[] $result */
        $result = $qb->getQuery()
            ->getResult();
        return $result;
    }

    /**
     * Get filtered users for autocomplete dropdown
     * @return User[]
     */
    public function getFilteredUsers(): array
    {
        if (empty($this->userSearch) || strlen($this->userSearch) < 2) {
            return [];
        }

        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.name LIKE :search OR u.email LIKE :search')
            ->setParameter('search', '%' . $this->userSearch . '%')
            ->orderBy('u.name', 'ASC')
            ->setMaxResults(10);

        /** @var User[] $result */
        $result = $qb->getQuery()
            ->getResult();
        return $result;
    }

    /**
     * Get selected lesson IDs as array
     * @return array<string>
     */
    public function getSelectedLessonIdsArray(): array
    {
        try {
            $decoded = json_decode($this->selectedLessonIds, true);
            if (! is_array($decoded)) {
                return [];
            }
            /** @var list<string> $ids */
            $ids = array_values(array_filter($decoded, static fn($v) => is_string($v)));
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
        $ids = $this->getSelectedLessonIdsArray();
        if (empty($ids)) {
            return [];
        }

        $ulidIds = array_map(fn($id) => Ulid::fromString($id), $ids);
        return $this->lessonRepository->findBy([
            'id' => $ulidIds,
        ]);
    }

    public function getSelectedUser(): ?User
    {
        if (! $this->selectedUserId) {
            return null;
        }

        try {
            $ulid = Ulid::fromString($this->selectedUserId);
            return $this->userRepository->find($ulid);
        } catch (\Exception) {
            return null;
        }
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
        $selectedIds = $this->getSelectedLessonIdsArray();

        if (! in_array($lessonId, $selectedIds, true)) {
            $selectedIds[] = $lessonId;
            $this->selectedLessonIds = (string) json_encode($selectedIds);
        }
    }

    #[LiveAction]
    public function removeLesson(#[LiveArg] string $lessonId): void
    {
        $selectedIds = $this->getSelectedLessonIdsArray();
        $selectedIds = array_values(array_filter($selectedIds, fn($id) => $id !== $lessonId));
        $this->selectedLessonIds = (string) json_encode($selectedIds);
    }

    #[LiveAction]
    public function selectUser(#[LiveArg] string $userId): void
    {
        $this->selectedUserId = $userId;
        $this->userSearch = null; // Clear search after selection
    }

    #[LiveAction]
    public function clearUserSelection(): void
    {
        $this->selectedUserId = null;
        $this->userSearch = null;
    }

    #[LiveAction]
    public function createBooking(): void
    {
        try {
            // Validate required fields
            if (empty($this->getSelectedLessonIdsArray())) {
                $this->errorMessage = 'Please select at least one lesson';
                return;
            }

            if (! $this->selectedUserId) {
                $this->errorMessage = 'Please select a user';
                return;
            }

            $user = $this->getSelectedUser();
            if (! $user) {
                $this->errorMessage = 'Selected user not found';
                return;
            }

            $lessons = $this->getSelectedLessons();
            if (empty($lessons)) {
                $this->errorMessage = 'Selected lessons not found';
                return;
            }

            // Create payment if amount is provided
            $payment = null;
            if ($this->amount && $this->amount > 0) {
                // Create Money object using PLN currency
                $moneyAmount = Money::of($this->amount, 'PLN');
                $payment = new Payment($user, $moneyAmount);
                $payment->setStatus(Payment::STATUS_PAID);

                $this->entityManager->persist($payment);
            }

            // Create booking with all lessons
            $booking = new Booking($user, $payment, ...$lessons);

            // Set booking status to active since it's manually created
            $booking->setStatus(Booking::STATUS_ACTIVE);

            if ($this->notes) {
                $booking->setNotes($this->notes);
            }

            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            // Send success message
            $this->successMessage = 'Manual booking created successfully';
            $this->errorMessage = null;

            // Reset form and close modal
            $this->resetForm();
            $this->modalOpened = false;

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to create booking: ' . $e->getMessage();
            $this->successMessage = null;
        }
    }

    private function resetForm(): void
    {
        $this->selectedLessonIds = '[]';
        $this->lessonSearch = null;
        $this->selectedUserId = null;
        $this->userSearch = null;
        $this->bookingType = 'single';
        $this->amount = null;
        $this->paymentMethod = 'cash';
        $this->notes = null;
    }

    public function isFormValid(): bool
    {
        return ! empty($this->getSelectedLessonIdsArray()) && ! empty($this->selectedUserId);
    }

    public function getTotalSelectedLessons(): int
    {
        return count($this->getSelectedLessonIdsArray());
    }

    /**
     * @return array<string, string>
     */
    public function getBookingTypeOptions(): array
    {
        return [
            'single' => 'Single Lesson',
            'carnet' => 'Carnet (Multiple Lessons)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getPaymentMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'online' => 'Online Payment',
        ];
    }
}
