<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Payment;
use App\Tests\Assembler\PaymentAssembler;
use PHPUnit\Framework\TestCase;

final class PaymentExpiryTest extends TestCase
{
    public function testExpiresThirtyWallClockMinutesAfterCreation(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-03 23:50:00', new \DateTimeZone('UTC'));
        $payment = PaymentAssembler::new()->withCreatedAt($createdAt)->assemble();

        static::assertSame(30, Payment::VALIDITY_MINUTES);
        static::assertEquals(
            new \DateTimeImmutable('2025-01-04 00:20:00', new \DateTimeZone('UTC')),
            $payment->getExpiresAt(),
        );
    }
}
