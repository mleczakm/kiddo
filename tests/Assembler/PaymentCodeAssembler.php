<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Payment;
use App\Entity\PaymentCode;

/**
 * @extends EntityAssembler<PaymentCode>
 */
class PaymentCodeAssembler extends EntityAssembler
{
    public function withId(int $id): static
    {
        return $this->with('id', $id);
    }

    public function withCode(string $code): static
    {
        return $this->with('code', $code);
    }

    public function withPayment(Payment $payment): static
    {
        return $this->with('payment', $payment);
    }

    public function withCreatedAt(\DateTimeImmutable $createdAt): static
    {
        return $this->with('createdAt', $createdAt);
    }

    public function assemble(): PaymentCode
    {
        /** @var Payment $payment */
        $payment = $this->properties['payment'] ?? PaymentAssembler::new()->assemble();

        $paymentCode = new PaymentCode($payment);

        if (isset($this->properties['id'])) {
            $reflection = new \ReflectionClass($paymentCode);
            $property = $reflection->getProperty('id');
            $property->setValue($paymentCode, $this->properties['id']);
        }

        if (isset($this->properties['code'])) {
            $reflection = new \ReflectionClass($paymentCode);
            $property = $reflection->getProperty('code');
            $property->setValue($paymentCode, $this->properties['code']);
        }

        if (isset($this->properties['createdAt'])) {
            $reflection = new \ReflectionClass($paymentCode);
            $property = $reflection->getProperty('createdAt');
            $property->setValue($paymentCode, $this->properties['createdAt']);
        }

        return $paymentCode;
    }
}
