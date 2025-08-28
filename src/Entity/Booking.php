<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Booking
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REFUNDED = 'refunded';

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

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    private ?Lesson $rescheduledFrom = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $rescheduledBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rescheduleReason = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $cancelledBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $refundedBy = null;

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

    public function addLesson(Lesson $lesson): self
    {
        if (! $this->lessons->contains($lesson)) {
            $this->lessons[] = $lesson;
            $lesson->addBooking($this);
        }

        return $this;
    }

    public function removeLesson(Lesson $lesson): self
    {
        if ($this->lessons->removeElement($lesson)) {
            $lesson->removeBooking($this);
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
            [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED, self::STATUS_COMPLETED],
            true
        )) {
            throw new \InvalidArgumentException('Invalid booking status');
        }

        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function confirm(): self
    {
        return $this->setStatus(self::STATUS_CONFIRMED);
    }

    public function cancel(): self
    {
        return $this->setStatus(self::STATUS_CANCELLED);
    }

    public function complete(): self
    {
        return $this->setStatus(self::STATUS_COMPLETED);
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
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function canBeRescheduledFor(Lesson $lesson): bool
    {
        if (! $this->isConfirmed()) {
            return false;
        }

        return $this->lessons->contains($lesson);
    }

    public function canBeRescheduled(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_CONFIRMED;
    }

    public function canRequestRefund(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
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

    public function setRefundedBy(?User $user): self
    {
        $this->refundedBy = $user;
        return $this;
    }

    public function setCancelledBy(?User $user): self
    {
        $this->cancelledBy = $user;
        return $this;
    }

    public function setLesson(Lesson $lesson): self
    {
        $this->lessons->clear();
        $this->lessons->add($lesson);
        return $this;
    }

    public function setRescheduledFrom(?Lesson $lesson): self
    {
        $this->rescheduledFrom = $lesson;
        return $this;
    }

    public function setRescheduledBy(?User $user): self
    {
        $this->rescheduledBy = $user;
        return $this;
    }

    public function setRescheduleReason(?string $reason): self
    {
        $this->rescheduleReason = $reason;
        return $this;
    }
}
