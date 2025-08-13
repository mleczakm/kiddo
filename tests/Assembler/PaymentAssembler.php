<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\PaymentCode;
use App\Entity\Transfer;
use App\Entity\Payment;
use App\Entity\User;
use Brick\Money\Money;

/**
 * @extends EntityAssembler<Payment>
 */
class PaymentAssembler extends EntityAssembler
{
    /**
     * @var Transfer[]
     */
    private array $transfers = [];

    private ?PaymentCode $paymentCode = null;

    public function withPaymentCode(PaymentCode $paymentCode): static
    {
        $this->paymentCode = $paymentCode;

        return $this;
    }

    public function withId(string $id): static
    {
        return $this->with('id', $id);
    }

    public function withUser(User $user): static
    {
        return $this->with('user', $user);
    }

    public function withAmount(Money $amount): static
    {
        return $this->with('amount', $amount);
    }

    public function withStatus(string $status): static
    {
        return $this->with('status', $status);
    }

    public function withCreatedAt(\DateTimeImmutable $createdAt): static
    {
        return $this->with('createdAt', $createdAt);
    }

    public function assemble(): Payment
    {
        /** @var User $user */
        $user = $this->properties['user'] ?? UserAssembler::new()->assemble();

        /** @var Money $amount */
        $amount = $this->properties['amount'] ?? Money::of(100, 'PLN');

        $payment = new Payment($user, $amount);

        if (isset($this->properties['id'])) {
            $reflection = new \ReflectionClass($payment);
            $property = $reflection->getProperty('id');
            $property->setAccessible(true);
            $property->setValue($payment, $this->properties['id']);
        }

        if (isset($this->properties['status'])) {
            $reflection = new \ReflectionClass($payment);
            $property = $reflection->getProperty('status');
            $property->setAccessible(true);
            $property->setValue($payment, $this->properties['status']);
        }

        if (isset($this->properties['createdAt'])) {
            $reflection = new \ReflectionClass($payment);
            $property = $reflection->getProperty('createdAt');
            $property->setAccessible(true);
            $property->setValue($payment, $this->properties['createdAt']);
        }

        foreach ($this->transfers as $transfer) {
            $payment->addTransfer($transfer);
        }

        if ($this->paymentCode) {
            $payment->setPaymentCode($this->paymentCode);
        }

        return $payment;
    }

    public function withTransfers(Transfer ...$transfers): static
    {
        $this->transfers = $transfers;

        return $this;
    }
}
