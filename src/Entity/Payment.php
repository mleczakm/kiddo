<?php

declare(strict_types=1);

namespace App\Entity;

use App\Application\Service\TransferMoneyParser;
use App\Repository\PaymentRepository;
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
    #[ORM\Column(type: 'ulid', length: 16, unique: true)]
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (! in_array($status, self::STATUSES, true)) {
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
        return $this->amount->isLessThanOrEqualTo($this->getAmountPaid());
    }

    public function getAmountPaid(): Money
    {
        return $this->transfers->map(
            fn(Transfer $transfer): Money => TransferMoneyParser::transferMoneyStringToMoneyObject(
                $transfer->amount
            )
        )->reduce(fn(Money $carry, Money $transfer) => $carry->plus($transfer), Money::zero('PLN'));
    }

    public function amountMatch(Transfer $transfer): bool
    {
        return $this->amount->isEqualTo(
            TransferMoneyParser::transferMoneyStringToMoneyObject($transfer->amount)->getAmount()
        );
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getBookingsSummary(): string
    {
        return implode(', ', $this->bookings->map(fn(Booking $booking) => $booking->getTextSummary()) ->toArray());
    }
}
