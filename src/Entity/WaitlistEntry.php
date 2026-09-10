<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\WaitlistEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

/**
 * A logged-in parent's request to be notified when a seat frees up on a full
 * {@see Lesson}. Entries form a per-lesson FIFO queue (`queuedAt`). When a seat
 * frees, the head of the queue is moved to `offered` and held for
 * {@see self::OFFER_TTL_MINUTES}; if not claimed (a booking placed) in that
 * window it `expires` and the next entry is offered.
 */
#[ORM\Entity(repositoryClass: WaitlistEntryRepository::class)]
#[ORM\Table(name: 'waitlist_entry')]
#[ORM\Index(columns: ['lesson_id', 'status', 'queued_at'], name: 'idx_waitlist_lesson_status_queued')]
#[ORM\Index(columns: ['status', 'offer_expires_at'], name: 'idx_waitlist_status_offer_expires')]
class WaitlistEntry
{
    public const string STATUS_WAITING = 'waiting';

    public const string STATUS_OFFERED = 'offered';

    public const string STATUS_CLAIMED = 'claimed';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_CANCELED = 'canceled';

    /** @var list<string> */
    public const array ACTIVE_STATUSES = [self::STATUS_WAITING, self::STATUS_OFFERED];

    public const int OFFER_TTL_MINUTES = 60;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_WAITING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $queuedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $offeredAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $offerExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Lesson::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Lesson $lesson,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(type: 'string', length: 255)]
        private string $emailSnapshot,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $nameSnapshot = null,
    ) {
        $this->id = new Ulid();
        $this->queuedAt = Clock::get()->now();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getLesson(): Lesson
    {
        return $this->lesson;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEmailSnapshot(): string
    {
        return $this->emailSnapshot;
    }

    public function getNameSnapshot(): ?string
    {
        return $this->nameSnapshot;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getQueuedAt(): \DateTimeImmutable
    {
        return $this->queuedAt;
    }

    public function getOfferExpiresAt(): ?\DateTimeImmutable
    {
        return $this->offerExpiresAt;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isOffered(): bool
    {
        return $this->status === self::STATUS_OFFERED;
    }

    public function isOfferExpired(\DateTimeImmutable $now): bool
    {
        return (
            $this->status === self::STATUS_OFFERED
            && $this->offerExpiresAt !== null
            && $this->offerExpiresAt <= $now
        );
    }

    /** No-op unless currently `waiting`. */
    public function offer(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_WAITING) {
            return;
        }
        $this->status = self::STATUS_OFFERED;
        $this->offeredAt = $now;
        $this->offerExpiresAt = $now->modify(sprintf('+%d minutes', self::OFFER_TTL_MINUTES));
    }

    public function markNotified(\DateTimeImmutable $now): void
    {
        $this->notifiedAt = $now;
    }

    /** Close the entry because the parent took the seat. No-op if already closed. */
    public function claim(\DateTimeImmutable $now): void
    {
        if (!$this->isActive()) {
            return;
        }
        $this->status = self::STATUS_CLAIMED;
        $this->closedAt = $now;
    }

    /** No-op unless currently holding an `offered` seat. */
    public function expire(\DateTimeImmutable $now): void
    {
        if ($this->status !== self::STATUS_OFFERED) {
            return;
        }
        $this->status = self::STATUS_EXPIRED;
        $this->closedAt = $now;
    }

    /** Parent left the queue. No-op if already closed. */
    public function cancel(\DateTimeImmutable $now): void
    {
        if (!$this->isActive()) {
            return;
        }
        $this->status = self::STATUS_CANCELED;
        $this->closedAt = $now;
    }
}
