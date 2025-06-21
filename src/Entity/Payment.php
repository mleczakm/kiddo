<?php

declare(strict_types=1);

namespace App\Entity;

use App\Application\Service\TransferMoneyParser;
use Brick\Money\Money;
use Brick\Money\MoneyBag;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Workflow\WorkflowInterface;

#[ORM\Entity]
class Payment
{
    // Statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_EXPIRED = 'expired';

    // Transitions
    public const TRANSITION_PAY = 'pay';

    public const TRANSITION_FAIL = 'fail';

    public const TRANSITION_REFUND = 'refund';

    public const TRANSITION_EXPIRE = 'expire';

    // List of all statuses for validation
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_REFUNDED,
        self::STATUS_EXPIRED,
    ];

    // List of all transitions for validation
    public const TRANSITIONS = [
        self::TRANSITION_PAY,
        self::TRANSITION_FAIL,
        self::TRANSITION_REFUND,
        self::TRANSITION_EXPIRE,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToOne(targetEntity: PaymentCode::class, mappedBy: 'payment', cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
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
        private Money $amount
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->bookings = new ArrayCollection();
        $this->transfers = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid payment status: %s', $status));
        }

        $this->status = $status;
        return $this;
    }

    public function markAsPaid(): self
    {
        $this->status = self::STATUS_PAID;
        $this->paidAt = new \DateTimeImmutable();
        return $this;
    }

    public function canTransition(string $transition, WorkflowInterface $workflow): bool
    {
        return $workflow->can($this, $transition);
    }

    public function applyTransition(string $transition, WorkflowInterface $workflow): void
    {
        if ($this->canTransition($transition, $workflow)) {
            $workflow->apply($this, $transition);
        }
    }

    public function markAsFailed(): self
    {
        $this->status = self::STATUS_FAILED;
        return $this;
    }

    public function markAsRefunded(): self
    {
        $this->status = self::STATUS_REFUNDED;
        return $this;
    }

    public function markAsExpired(): self
    {
        $this->status = self::STATUS_EXPIRED;
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

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (! $this->bookings->contains($booking)) {
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

    public function setPaymentCode(PaymentCode $paymentCode): self
    {
        // set the owning side of the relation if necessary
        if ($paymentCode->getPayment() !== $this) {
            $paymentCode->setPayment($this);
        }

        $this->paymentCode = $paymentCode;
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
        if (! $this->transfers->contains($transfer)) {
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
        //sum amount of all transfers
        return $this->amount->isLessThanOrEqualTo(
            $this->transfers->map(
                fn(Transfer $transfer): Money => TransferMoneyParser::transferMoneyStringToMoneyObject(
                    $transfer->amount
                )
            )->reduce(
                fn(MoneyBag $carry, Money $transfer) => $carry->add($transfer),
                new MoneyBag()
            )->getAmount('PLN')
        );
    }
}
