<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\DTO\BookedLesson;
use App\Entity\DTO\LessonMap;
use App\Entity\DTO\RescheduledLesson;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Booking
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAST = 'past';

    // Add missing constants for compatibility
    public const STATUS_CONFIRMED = 'active'; // Alias for active

    public const STATUS_COMPLETED = 'past'; // Alias for past

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    /**
     * @var Collection<int|string, Lesson>
     */
    #[ORM\ManyToMany(targetEntity: Lesson::class, inversedBy: 'bookings')]
    private Collection $lessons;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json_document', nullable: true)]
    private ?LessonMap $lessonsMap = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $notes = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
        #[ORM\JoinColumn(nullable: false)]
        private User $user,
        #[ORM\ManyToOne(targetEntity: Payment::class, cascade: ['persist'], inversedBy: 'bookings')]
        public ?Payment $payment,
        Lesson ... $lessons
    ) {
        $this->id = new Ulid();
        $this->lessons = new ArrayCollection($lessons);
        $this->status = self::STATUS_PENDING;
        $this->createdAt = new \DateTimeImmutable();

        $this->lessonsMap = LessonMap::createFromBooking($this);
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return Collection<int|string, Lesson>
     */
    public function getLessons(): Collection
    {
        return $this->lessons;
    }

    /**
     * Add lesson to booking
     */
    public function addLesson(Lesson $lesson): self
    {
        if (! $this->lessons->contains($lesson)) {
            $this->lessons[] = $lesson;
            $lesson->addBooking($this);

            // Add to booked lessons map
            $bookedLesson = new BookedLesson($lesson->getId());
            $this->getLessonsMap()
                ->setLesson((string) $lesson->getId(), $bookedLesson);
        }

        return $this;
    }

    /**
     * Remove lesson from booking
     */
    public function removeLesson(Lesson $lesson): self
    {
        if ($this->lessons->removeElement($lesson)) {
            $lesson->removeBooking($this);

            $this->getLessonsMap()
                ->removeLesson($lesson->getId());
        }

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (! in_array(
            $status,
            [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_CANCELLED, self::STATUS_PAST],
            true
        )) {
            throw new \InvalidArgumentException('Invalid booking status: ' . $status);
        }

        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE], true);
    }

    public function canBeCompleted(): bool
    {
        return $this->getLessonsMap()
            ->active->isEmpty();
    }

    public function confirm(): self
    {
        return $this->setStatus(self::STATUS_ACTIVE);
    }

    public function cancel(?User $cancelledBy = null, ?string $reason = null): self
    {
        $this->setStatus(self::STATUS_CANCELLED);
        $this->cancelledBy = $cancelledBy;

        // Cancel all booked lessons using the map
        $this->getLessonsMap()
            ->cancelAllBookedLessons($reason);

        if ($reason) {
            $this->notes = $reason;
        }

        return $this;
    }

    public function complete(): self
    {
        return $this->setStatus(self::STATUS_PAST);
    }

    /**
     * Get booked lesson by lesson ID
     */
    public function getBookedLesson(string $lessonId): ?BookedLesson
    {
        return $this->getLessonsMap()
            ->getLesson($lessonId);
    }

    public function getTitle(): string
    {
        /** @var ?Lesson $lesson */
        $lesson = $this->lessons->first();
        if (! $lesson instanceof Lesson) {
            return '';
        }

        return $lesson->getMetadata()
            ->title ?? '';
    }

    /**
     * Cancel specific lesson
     */
    public function cancelLesson(string $lessonId, ?string $reason = null): bool
    {
        $result = $this->getLessonsMap()
            ->cancelLesson($lessonId, $reason);
        if ($result) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $result;
    }

    /**
     * Refund specific lesson
     */
    public function refundLesson(string $lessonId, ?string $reason = null): bool
    {
        $result = $this->getLessonsMap()
            ->refundLesson($lessonId, $reason);
        if ($result) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $result;
    }

    /**
     * Reschedule a specific lesson
     */
    public function rescheduleLesson(Lesson $from, Lesson $to, User $rescheduledBy): void
    {
        // Add the new lesson entity to the Doctrine collection
        $this->lessons->add($to);

        $lessonMap = $this->getLessonsMap();

        // Ensure the new lesson is present in the lessons and active maps
        $lessonMap->lessons->put($to->getId(), new BookedLesson($to->getId()));
        $lessonMap->active->put($to->getId(), new BookedLesson($to->getId()));

        // Safely remove the original lesson from active if present
        if ($lessonMap->active->hasKey($from->getId())) {
            $lessonMap->active->remove($from->getId());
        }

        // Mark the original lesson as cancelled due to reschedule,
        // keyed by the original (from) lesson id
        $lessonMap->cancelled->put(
            $from->getId(),
            new RescheduledLesson($to->getId(), $from->getId(), $rescheduledBy->getId() ?? 0, new \DateTimeImmutable())
        );

        $this->lessonsMap = $lessonMap;
    }

    /**
     * Check if booking has any active lessons
     */
    public function hasActiveBookedLessons(): bool
    {
        return $this->getLessonsMap()
            ->hasActiveBookedLessons();
    }

    public function getActiveBookedLessonEntities(): \Generator
    {
        yield from $this->getLessonsMap()
            ->active()
            ->map(fn(Ulid $key, BookedLesson $value) => $value->entity($this));
    }

    /**
     * Check if booking should be marked as past
     */
    public function shouldBeMarkedAsPast(): bool
    {
        if ($this->status === self::STATUS_PAST) {
            return false;
        }

        return $this->getLessonsMap()
            ->areAllActiveLessonsInPast($this);
    }

    /**
     * Get lessons that can be modified (future active lessons)
     * @return BookedLesson[]
     */
    public function getModifiableLessons(): array
    {
        return $this->getLessonsMap()
            ->getModifiableLessons($this);
    }

    /**
     * Get booking status summary
     * @return array{total: int, booked: int, cancelled: int, refunded: int, rescheduled: int}
     */
    public function getLessonStatusSummary(): array
    {
        return $this->getLessonsMap()
            ->getStatusSummary();
    }

    /**
     * @return BookedLesson[]
     */
    public function getPastActiveLessons(): array
    {
        return $this->getLessonsMap()
            ->getPastActiveLessons($this);
    }

    /**
     * @return BookedLesson[]
     */
    public function getFutureActiveLessons(): array
    {
        return $this->getLessonsMap()
            ->getFutureActiveLessons($this);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;
        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPast(): bool
    {
        return $this->status === self::STATUS_PAST;
    }

    public function canBeRescheduled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE], true);
    }

    public function canRequestRefund(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getLesson(): ?Lesson
    {
        return $this->lessons->first() ?: null;
    }

    public function getCancellationReason(): ?string
    {
        return $this->notes;
    }

    public function getAmountPaid(): int
    {
        if ($this->payment === null) {
            return 0;
        }

        return $this->payment->getAmount()
            ->getMinorAmount()
            ->toInt();
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function setCancelledBy(?User $user): self
    {
        $this->cancelledBy = $user;
        return $this;
    }

    /**
     * Get the booked lessons map
     */
    public function getLessonsMap(): LessonMap
    {
        if ($this->lessonsMap === null) {
            // Initialize from current lessons if missing (e.g., legacy records)
            $this->lessonsMap = LessonMap::createFromBooking($this);
        }
        return $this->lessonsMap = clone $this->lessonsMap;
    }
}
