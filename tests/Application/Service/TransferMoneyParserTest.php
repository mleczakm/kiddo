<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\TransferMoneyParser;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransferMoneyParserTest extends TestCase
{
    #[DataProvider('moneyStringProvider')]
    public function testTransferMoneyStringToMoneyObject(string $amount, Money $expected): void
    {
        $parser = new TransferMoneyParser();
        $result = $parser->transferMoneyStringToMoneyObject($amount);

        $this->assertTrue(
            $result->isEqualTo($expected),
            sprintf('Failed asserting that %s equals %s', $result->getAmount(), $expected->getAmount())
        );
        $this->assertSame('PLN', (string) $result->getCurrency());
    }

    public static function moneyStringProvider(): array
    {
        return [
            ['0.00', Money::of(0, 'PLN')],
            ['15 011,75', Money::of('15011.75', 'PLN')],
            ['2 000,00', Money::of(2000, 'PLN')],
            ['200,00', Money::of(200, 'PLN')],
            ['0,01', Money::of('0.01', 'PLN')],
            ['1 234 567,89', Money::of('1234567.89', 'PLN')],
            ['1.234.567,89', Money::of('1234567.89', 'PLN')],
            ['1,23', Money::of('1.23', 'PLN')],
            [',5', Money::of('0.50', 'PLN')],
            ['5,', Money::of('5.00', 'PLN')],
            ['5', Money::of('5.00', 'PLN')],
        ];
    }
}
