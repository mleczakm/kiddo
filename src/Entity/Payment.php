<?php

declare(strict_types=1);

namespace App\Entity;

use App\Application\Service\TransferMoneyParser;
use App\Infrastructure\Doctrine\Repository\PaymentRepository;
use Brick\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    // Statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REFUND_REQUESTED = 'refund_requested';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    // Transitions
    public const TRANSITION_PAY = 'pay';

    public const TRANSITION_FAIL = 'fail';

    public const TRANSITION_REQUEST_REFUND = 'request_refund';

    public const TRANSITION_REFUND = 'refund';

    public const TRANSITION_DECLINE_REFUND = 'decline_refund';

    public const TRANSITION_CANCEL = 'cancel';

    public const TRANSITION_EXPIRE = 'expire';

    // List of all statuses for validation
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_REFUND_REQUESTED,
        self::STATUS_REFUNDED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    // List of all transitions for validation
    public const TRANSITIONS = [
        self::TRANSITION_PAY,
        self::TRANSITION_FAIL,
        self::TRANSITION_REQUEST_REFUND,
        self::TRANSITION_REFUND,
        self::TRANSITION_DECLINE_REFUND,
        self::TRANSITION_CANCEL,
        self::TRANSITION_EXPIRE,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16, unique: true)]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'string', length: 4, nullable: true)]
    private ?string $paymentCodeSnapshot = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $refundRequestedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $refundRequestedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $refundRequestMessage = null;

    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $refundRequestedViaUserPanel = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $statusChangedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $statusChangedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $statusNote = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToOne(
        targetEntity: PaymentCode::class,
        mappedBy: 'payment',
        cascade: [
            'persist',
            'remove',
        ],
        orphanRemoval: true,
    )]
    private ?PaymentCode $paymentCode = null;

    /**
     * @var Collection<int, Transfer>
     */
    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: Transfer::class)]
    private Collection $transfers;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false)]
        private User $user,
        #[ORM\Column(type: 'json_document')]
        private Money $amount,
        #[ORM\Column(type: 'string', enumType: PaymentMethod::class, nullable: true)]
        private ?PaymentMethod $method = null,
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->bookings = new ArrayCollection();
        $this->transfers = new ArrayCollection();
        $this->method ??= PaymentMethod::ONLINE;
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid payment status: %s', $status));
        }

        switch ($status) {
            case self::STATUS_PAID:
                // When marking as paid, prefer aligning paidAt with the original creation time
                // to keep week-based reporting stable (tests set createdAt within the target week).
                // Do not override an existing paidAt set elsewhere.
                if ($this->paidAt === null) {
                    $this->paidAt = $this->createdAt;
                }
                $this->paymentCode = null;
                break;
            default:
                break;
        }

        if ($this->status !== $status) {
            $this->statusChangedAt = new \DateTimeImmutable();
        }
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function recordRefundRequest(User $requestedBy, ?string $message, bool $viaUserPanel): self
    {
        $this->refundRequestedAt = new \DateTimeImmutable();
        $this->refundRequestedBy = $requestedBy;
        $this->refundRequestMessage = $message !== null && trim($message) !== '' ? trim($message) : null;
        $this->refundRequestedViaUserPanel = $viaUserPanel;

        return $this;
    }

    public function recordStatusDecision(User $changedBy, ?string $note): self
    {
        $this->statusChangedBy = $changedBy;
        $this->statusNote = $note !== null && trim($note) !== '' ? trim($note) : null;
        $this->statusChangedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function getRefundRequestedAt(): ?\DateTimeImmutable
    {
        return $this->refundRequestedAt;
    }

    public function getRefundRequestedBy(): ?User
    {
        return $this->refundRequestedBy;
    }

    public function getRefundRequestMessage(): ?string
    {
        return $this->refundRequestMessage;
    }

    public function isRefundRequestedViaUserPanel(): bool
    {
        return $this->refundRequestedViaUserPanel;
    }

    public function getStatusChangedAt(): ?\DateTimeImmutable
    {
        return $this->statusChangedAt;
    }

    public function getStatusChangedBy(): ?User
    {
        return $this->statusChangedBy;
    }

    public function getStatusNote(): ?string
    {
        return $this->statusNote;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings[] = $booking;
            $booking->setPayment($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getPayment() === $this) {
                $booking->setPayment(null);
            }
        }

        return $this;
    }

    public function getPaymentCode(): ?PaymentCode
    {
        return $this->paymentCode;
    }

    public function getPaymentCodeSnapshot(): ?string
    {
        return $this->paymentCodeSnapshot;
    }

    public function setPaymentCode(PaymentCode $paymentCode): self
    {
        // set the owning side of the relation if necessary
        if ($paymentCode->getPayment() !== $this) {
            $paymentCode->setPayment($this);
        }

        $this->paymentCode = $paymentCode;
        $this->paymentCodeSnapshot = $paymentCode->getCode();
        return $this;
    }

    /**
     * @return Collection<int, Transfer>
     */
    public function getTransfers(): Collection
    {
        return $this->transfers;
    }

    public function addTransfer(Transfer $transfer): self
    {
        if (!$this->transfers->contains($transfer)) {
            $this->transfers->add($transfer);
            $transfer->setPayment($this);
        }

        return $this;
    }

    public function removeTransfer(Transfer $transfer): self
    {
        if ($this->transfers->removeElement($transfer)) {
            // set the owning side to null (unless already changed)
            if ($transfer->getPayment() === $this) {
                $transfer->setPayment(null);
            }
        }

        return $this;
    }

    public function isPaid(): bool
    {
        return $this->amount->isLessThanOrEqualTo($this->getAmountPaid());
    }

    public function getAmountPaid(): Money
    {
        return $this->transfers->map(static fn(Transfer $transfer): Money => TransferMoneyParser::transferMoneyStringToMoneyObject($transfer->amount))->reduce(
            static fn(Money $carry, Money $transfer) => $carry->plus($transfer),
            Money::zero('PLN'),
        );
    }

    public function amountMatch(Transfer $transfer): bool
    {
        return $this->amount->isEqualTo(
            TransferMoneyParser::transferMoneyStringToMoneyObject($transfer->amount)->getAmount(),
        );
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getBookingsSummary(): string
    {
        return implode(
            ', ',
            $this->bookings->map(static fn(Booking $booking) => $booking->getTextSummary())->toArray(),
        );
    }

    public function getMethod(): ?PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function requiresApproval(): bool
    {
        return $this->method === PaymentMethod::PAY_ON_PLACE;
    }
}
