<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Twig;

use PHPUnit\Framework\Attributes\Group;
use App\Infrastructure\Twig\MoneyExtension;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class MoneyExtensionTest extends TestCase
{
    public function testFormatMoney(): void
    {
        $extension = new MoneyExtension();
        $money = Money::of(5000, 'PLN');

        $this->assertEquals('5 000 zł', $extension->formatMoney($money));
    }
}
