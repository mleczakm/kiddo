<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use PHPUnit\Framework\Attributes\Group;
use App\Application\Service\MoneyInputParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class MoneyInputParserTest extends TestCase
{
    #[Test]
    #[DataProvider('validAmountProvider')]
    public function parsesValidAmounts(string $input, string $expected): void
    {
        self::assertSame($expected, MoneyInputParser::parse($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validAmountProvider(): array
    {
        return [
            'plain integer' => ['55', '55'],
            'dot decimal' => ['55.50', '55.50'],
            'comma decimal' => ['55,50', '55.50'],
            'single fraction digit' => ['55,5', '55.5'],
            'trailing separator' => ['55,', '55'],
            'leading separator' => [',50', '0.50'],
            'space thousands separator' => ['1 000,00', '1000.00'],
            'dot thousands, comma decimal' => ['1.000,50', '1000.50'],
            'comma thousands, dot decimal' => ['1,000.50', '1000.50'],
            'zł suffix' => ['100 zł', '100'],
            'PLN suffix' => ['100 PLN', '100'],
            'surrounding whitespace' => ['  42  ', '42'],
        ];
    }

    #[Test]
    public function returnsNullForNull(): void
    {
        self::assertNull(MoneyInputParser::parse(null));
    }

    #[Test]
    public function returnsNullForEmptyOrWhitespaceOnlyInput(): void
    {
        self::assertNull(MoneyInputParser::parse(''));
        self::assertNull(MoneyInputParser::parse('   '));
    }

    #[Test]
    #[DataProvider('invalidAmountProvider')]
    public function rejectsInvalidAmounts(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MoneyInputParser::parse($input);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidAmountProvider(): array
    {
        return [
            'non-numeric' => ['abc'],
            'negative' => ['-5'],
            'more than two decimal places' => ['55,123'],
            'only separators' => [',.'],
        ];
    }
}
