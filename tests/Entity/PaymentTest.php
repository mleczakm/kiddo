<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Payment;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Currency;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    public function testIsPaidFalseOnMissingTransfers(): void
    {
        $payment = new Payment(UserAssembler::new()->assemble(), Money::of(100, Currency::ofCountry('PL')));
        $this->assertFalse($payment->isPaid());
    }

    public function testIsPaidTrueOnTransfers(): void
    {
        $payment = new Payment(UserAssembler::new()->assemble(), Money::of(100, Currency::ofCountry('PL')));
        $payment->addTransfer(TransferAssembler::new()->withAmount('100.00')->assemble());

        $this->assertTrue($payment->isPaid());
    }
}
