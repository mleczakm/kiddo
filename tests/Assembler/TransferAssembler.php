<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Payment;
use App\Entity\Transfer;

/**
 * @extends EntityAssembler<Transfer>
 */
class TransferAssembler extends EntityAssembler
{
    public function withId(int $id): static
    {
        return $this->with('id', $id);
    }

    public function withAccountNumber(string $accountNumber): static
    {
        return $this->with('accountNumber', $accountNumber);
    }

    public function withSender(string $sender): static
    {
        return $this->with('sender', $sender);
    }

    public function withTitle(string $title): static
    {
        return $this->with('title', $title);
    }

    public function withAmount(string $amount): static
    {
        return $this->with('amount', $amount);
    }

    public function withTransferredAt(\DateTimeImmutable $transferredAt): static
    {
        return $this->with('transferredAt', $transferredAt);
    }

    public function withPayment(Payment $payment): static
    {
        return $this->with('payment', $payment);
    }

    public function assemble(): Transfer
    {
        /** @var string $accountNumber */
        $accountNumber = $this->properties['accountNumber'] ?? 'PL61109010140000071219812874';
        /** @var string $sender */
        $sender = $this->properties['sender'] ?? 'John Doe';
        /** @var string $title */
        $title = $this->properties['title'] ?? 'Payment';
        /** @var string $amount */
        $amount = $this->properties['amount'] ?? '100.00';
        /** @var \DateTimeImmutable $transferredAt */
        $transferredAt = $this->properties['transferredAt'] ?? new \DateTimeImmutable();

        $transfer = new Transfer(
            accountNumber: $accountNumber,
            sender: $sender,
            title: $title,
            amount: $amount,
            transferredAt: $transferredAt
        );

        if (isset($this->properties['id'])) {
            $reflection = new \ReflectionClass($transfer);
            $property = $reflection->getProperty('id');
            $property->setAccessible(true);
            $property->setValue($transfer, $this->properties['id']);
        }

        if (isset($this->properties['payment'])) {
            $reflection = new \ReflectionClass($transfer);
            $property = $reflection->getProperty('payment');
            $property->setAccessible(true);
            $property->setValue($transfer, $this->properties['payment']);
        }

        return $transfer;
    }
}
