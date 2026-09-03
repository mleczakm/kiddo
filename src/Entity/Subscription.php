<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

/**
 * An active monthly-subscription ticket (TicketType::MONTHLY) for a recurring
 * (WEEKLY) Series. Each month's charge is a plain Payment plus a Booking over
 * that month's series lessons, issued by IssueSubscriptionCharges;
 * `lastChargedPeriod` (a "Y-m" marker) keeps that idempotent. The monthly
 * price is a snapshot of the series' MONTHLY TicketOption at purchase time.
 * Whole feature gated behind the `subscriptions` flag, off on prod.
 */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** The most recent "Y-m" period an invoice was issued for. */
    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    private ?string $lastChargedPeriod = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: [
        'default' => 1,
    ])]
    private int $version = 1;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency = 'PLN';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false)]
        private User $user,
        #[ORM\ManyToOne(targetEntity: Series::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Series $series,
        #[ORM\Column(type: 'integer')]
        private int $monthlyRateMinor,
        #[ORM\ManyToOne(targetEntity: Child::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Child $child = null,
    ) {
        $this->id = new Ulid();
        $this->createdAt = Clock::get()->now();
        $this->startsAt = $this->createdAt;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSeries(): Series
    {
        return $this->series;
    }

    public function getChild(): ?Child
    {
        return $this->child;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getMonthlyRateMinor(): int
    {
        return $this->monthlyRateMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastChargedPeriod(): ?string
    {
        return $this->lastChargedPeriod;
    }

    /**
     * True when active and no invoice has been issued yet for the given "Y-m"
     * period (and the period is not before the subscription started).
     */
    public function needsChargeFor(\DateTimeImmutable $period): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $key = $period->format('Y-m');
        if ($this->lastChargedPeriod !== null && $this->lastChargedPeriod >= $key) {
            return false;
        }

        return $this->startsAt->format('Y-m') <= $key;
    }

    public function markCharged(\DateTimeImmutable $period): void
    {
        $this->lastChargedPeriod = $period->format('Y-m');
    }

    public function pause(): void
    {
        if ($this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_PAUSED;
        }
    }

    public function resume(): void
    {
        if ($this->status === self::STATUS_PAUSED) {
            $this->status = self::STATUS_ACTIVE;
        }
    }

    public function end(): void
    {
        $this->status = self::STATUS_ENDED;
        $this->endsAt = Clock::get()->now();
    }
}
