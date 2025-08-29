<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\TicketOption;
use App\Entity\TicketType;
use App\Entity\TicketReschedulePolicy;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

class TicketOptionTest extends TestCase
{
    public function testTicketOptionProperties(): void
    {
        $option = new TicketOption(
            TicketType::CARNET_4,
            Money::of(180, 'PLN'),
            'Testowy opis',
            TicketReschedulePolicy::UNLIMITED_24H_BEFORE
        );

        $this->assertSame(TicketType::CARNET_4, $option->type);
        $this->assertEquals(Money::of(180, 'PLN'), $option->price);
        $this->assertSame('Testowy opis', $option->description);
        $this->assertSame(TicketReschedulePolicy::UNLIMITED_24H_BEFORE, $option->reschedulePolicy);
    }
}
