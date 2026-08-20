<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\RefundRequestRepository;
use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: RefundRequestRepository::class)]
#[ORM\Table(name: 'refund_request')]
#[ORM\Index(columns: ['status', 'requested_at'], name: 'idx_refund_request_status_requested_at')]
class RefundRequest
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_DECLINED,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $decisionNote = null;

    #[ORM\Column(type: 'json_document', nullable: true)]
    private ?Money $approvedAmount = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Payment::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Payment $payment,
        #[ORM\ManyToOne(targetEntity: Booking::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Booking $booking,
        #[ORM\ManyToOne(targetEntity: Lesson::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Lesson $lesson,
        #[ORM\Column(type: 'json_document')]
        private Money $requestedAmount,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $requestedBy,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $requestMessage,
    ) {
        $this->id = new Ulid();
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function getLesson(): ?Lesson
    {
        return $this->lesson;
    }

    public function getRequestedAmount(): Money
    {
        return $this->requestedAmount;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getRequestMessage(): ?string
    {
        return $this->requestMessage;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getDecidedBy(): ?User
    {
        return $this->decidedBy;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function getDecisionNote(): ?string
    {
        return $this->decisionNote;
    }

    public function getApprovedAmount(): ?Money
    {
        return $this->approvedAmount;
    }

    public function approve(User $decidedBy, ?string $note, ?Money $approvedAmount = null): void
    {
        if (!$this->isPending()) {
            throw new \RuntimeException(sprintf(
                'Refund request %s was already %s and cannot be decided again.',
                $this->id,
                $this->status,
            ));
        }

        $this->status = self::STATUS_APPROVED;
        $this->decidedBy = $decidedBy;
        $this->decidedAt = new \DateTimeImmutable();
        $this->decisionNote = $note !== null && trim($note) !== '' ? trim($note) : null;
        $this->approvedAmount = $approvedAmount ?? $this->requestedAmount;
    }

    public function decline(User $decidedBy, ?string $note): void
    {
        if (!$this->isPending()) {
            throw new \RuntimeException(sprintf(
                'Refund request %s was already %s and cannot be decided again.',
                $this->id,
                $this->status,
            ));
        }

        $this->status = self::STATUS_DECLINED;
        $this->decidedBy = $decidedBy;
        $this->decidedAt = new \DateTimeImmutable();
        $this->decisionNote = $note !== null && trim($note) !== '' ? trim($note) : null;
    }
}
