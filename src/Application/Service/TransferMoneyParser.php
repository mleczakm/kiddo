<?php

declare(strict_types=1);

namespace App\Application\Service;

use Brick\Money\Money;

class TransferMoneyParser
{
    public static function transferMoneyStringToMoneyObject(string $amount): Money
    {
        // Remove all non-digit and non-comma characters
        $cleaned = preg_replace('/[^\d,]/', '', $amount);

        // Replace comma with dot to create a valid decimal number
        $number = str_replace(',', '.', $cleaned ?? '');

        // If the number starts with a dot, add leading zero
        if (str_starts_with($number, '.')) {
            $number = '0' . $number;
        }

        // If there's no decimal part, add .00
        if (! str_contains($number, '.')) {
            $number .= '.00';
        }

        // Ensure exactly 2 decimal places
        $parts = explode('.', $number, 2);
        if (strlen($parts[1]) > 2) {
            // If more than 2 decimal places, we'll let Money handle the rounding
            $number = $parts[0] . '.' . substr($parts[1], 0, 2);
        } elseif (strlen($parts[1]) === 1) {
            // If only 1 decimal place, add a trailing zero
            $number .= '0';
        }

        return Money::of($number, 'PLN');
    }
}
