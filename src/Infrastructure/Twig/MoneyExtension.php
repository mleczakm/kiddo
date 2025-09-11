<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use Brick\Money\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [new TwigFilter('money', $this->formatMoney(...))];
    }

    public function formatMoney(Money $money): string
    {
        $formatter = new \NumberFormatter('pl_PL', \NumberFormatter::CURRENCY);
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, 'zł');
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);

        return $money->formatWith($formatter);
    }
}
