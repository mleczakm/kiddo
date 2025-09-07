<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\Transfer;
use App\Entity\User;
use Brick\Money\Money as BrickMoney;

class PaymentAssembler
{
    private string $id;

    private User $user;

    private BrickMoney $amount;

    private string $status = Payment::STATUS_PENDING;

    private \DateTimeImmutable $createdAt;

    private ?PaymentCode $paymentCode = null;

    /**
     * @var array<Transfer>
     */
    private array $transfers = [];

    public static function new(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;
        return $clone;
    }

    public function withUser(User $user): self
    {
        $clone = clone $this;
        $clone->user = $user;
        return $clone;
    }

    public function withAmount(BrickMoney $amount): self
    {
        $clone = clone $this;
        $clone->amount = $amount;
        return $clone;
    }

    public function withStatus(string $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $clone = clone $this;
        $clone->createdAt = $createdAt;
        return $clone;
    }

    public function withPaymentCode(PaymentCode $paymentCode): self
    {
        $clone = clone $this;
        $clone->paymentCode = $paymentCode;
        return $clone;
    }

    public function withTransfers(Transfer ...$transfers): self
    {
        $clone = clone $this;
        $clone->transfers = array_merge($clone->transfers, $transfers);
        return $clone;
    }

    public function assemble(): Payment
    {
        // Set default user if not provided
        $user = $this->user ?? UserAssembler::new()->assemble();
        $amount = $this->amount ?? BrickMoney::of(100, 'PLN');
        $createdAt = $this->createdAt ?? new \DateTimeImmutable();

        $payment = new Payment($user, $amount);

        // Set properties via reflection since they are private
        $reflection = new \ReflectionClass($payment);

        if (isset($this->id)) {
            $property = $reflection->getProperty('id');
            $property->setAccessible(true);
            $property->setValue($payment, $this->id);
        }

        $statusProperty = $reflection->getProperty('status');
        $statusProperty->setAccessible(true);
        $statusProperty->setValue($payment, $this->status);

        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($payment, $createdAt);

        // Add payment code if provided
        if ($this->paymentCode) {
            $payment->setPaymentCode($this->paymentCode);
        }

        // Add transfers
        foreach ($this->transfers as $transfer) {
            $payment->addTransfer($transfer);
        }

        return $payment;
    }
}
