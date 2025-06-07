<?php

declare(strict_types=1);

namespace App\Entity;

use Brick\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Payment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\Column(type: 'json_document')]
    private Money $amount;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToOne(targetEntity: PaymentCode::class, mappedBy: 'payment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?PaymentCode $paymentCode;

    public function __construct(
        User $user,
        Money $amount,
        ?\DateTimeImmutable $expiresAt
    ) {
        $this->id = new Ulid();
        $this->user = $user;
        $this->amount = $amount;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
        $this->bookings = new ArrayCollection();
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
        if (!in_array($status, [
            self::STATUS_PENDING,
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_REFUNDED,
            self::STATUS_EXPIRED
        ], true)) {
            throw new \InvalidArgumentException('Invalid payment status');
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

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
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

    public function setPaymentCode(PaymentCode $paymentCode): self
    {
        // set the owning side of the relation if necessary
        if ($paymentCode->getPayment() !== $this) {
            $paymentCode->setPayment($this);
        }

        $this->paymentCode = $paymentCode;
        return $this;
    }
}
