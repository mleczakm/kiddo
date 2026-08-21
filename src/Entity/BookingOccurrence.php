<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'booking_occurrence')]
#[ORM\UniqueConstraint(name: 'uniq_booking_occurrence_booking_lesson', columns: ['booking_id', 'lesson_id'])]
class BookingOccurrence
{
    public const string STATUS_RESERVED = 'reserved';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_ATTENDED = 'attended';
    public const string STATUS_CANCELLED = 'cancelled';
    public const string STATUS_RESCHEDULED = 'rescheduled';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'occurrences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Booking $booking;

    public function __construct(
        Booking $booking,
        #[ORM\ManyToOne(targetEntity: Lesson::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private Lesson $lesson,
        #[ORM\Column(length: 20)]
        private string $status = self::STATUS_RESERVED,
        #[ORM\ManyToOne(targetEntity: Lesson::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Lesson $rescheduledTo = null,
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $cancelledAt = null,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $cancelledBy = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $cancellationReason = null,
    ) {
        $this->id = new Ulid();
        $this->booking = $booking;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getLesson(): Lesson
    {
        return $this->lesson;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRescheduledTo(): ?Lesson
    {
        return $this->rescheduledTo;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_RESERVED, self::STATUS_CONFIRMED], true);
    }

    public function confirm(): void
    {
        if ($this->status === self::STATUS_RESERVED) {
            $this->status = self::STATUS_CONFIRMED;
        }
    }

    public function attend(): void
    {
        if ($this->isActive()) {
            $this->status = self::STATUS_ATTENDED;
        }
    }

    public function cancel(?string $reason, ?User $by): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = new \DateTimeImmutable();
        $this->cancelledBy = $by;
        $this->cancellationReason = $reason;
        return true;
    }

    public function rescheduleTo(Lesson $lesson): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        $this->status = self::STATUS_RESCHEDULED;
        $this->rescheduledTo = $lesson;
        return true;
    }

    public function reactivate(): void
    {
        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_RESCHEDULED], true)) {
            $this->status = self::STATUS_RESERVED;
            $this->rescheduledTo = null;
            $this->cancelledAt = null;
            $this->cancelledBy = null;
            $this->cancellationReason = null;
        }
    }
}
