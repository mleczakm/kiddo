<?php

declare(strict_types=1);

namespace App\Domain;

use Brick\Money\Money;

class TransferMoneyParser
{
    /**
     * @throws \Brick\Math\Exception\NumberFormatException
     * @throws \Brick\Math\Exception\RoundingNecessaryException
     * @throws \Brick\Money\Exception\UnknownCurrencyException
     */
    public static function transferMoneyStringToMoneyObject(string $amount): Money
    {
        // Verify if it is not a default format like 5.00, 100.00, etc.
        if (is_numeric($amount)) {
            return Money::of($amount, 'PLN');
        }

        // Remove all non-digit and non-comma characters
        $cleaned = preg_replace('/[^\d,]/', '', $amount);
        // Replace multiple commas with a single one
        /** @var string $cleaned */
        $cleaned = preg_replace('/,+/', ',', $cleaned ?? '');

        if ($cleaned === '' || $cleaned === ',') {
            return Money::of('0.00', 'PLN');
        }

        // Replace comma with dot to create a valid decimal number
        $number = str_replace(',', '.', $cleaned);

        // If the number starts with a dot, add leading zero
        if (str_starts_with($number, '.')) {
            $number = '0' . $number;
        }

        // If there's no decimal part, add .00
        if (!str_contains($number, '.')) {
            $number .= '.00';
        }

        // Ensure exactly 2 decimal places
        $parts = explode('.', $number, 2);

        // More than 2 decimal places are truncated, we'll let Money handle the rounding.
        // Missing decimal places are padded with zeros.
        $number = $parts[0] . '.' . str_pad(substr($parts[1], 0, 2), 2, '0');

        return Money::of($number, 'PLN');
    }
}
