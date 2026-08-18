<?php

declare(strict_types=1);

namespace App\Application\Service;

/**
 * Normalizes a monetary amount typed by a user (Polish locale conventions:
 * comma or dot as the decimal separator, optional spaces as thousands
 * separators, an optional "zł"/"PLN" suffix) into a plain decimal string
 * accepted by Brick\Money\Money::of().
 */
final class MoneyInputParser
{
    /**
     * @return non-empty-string|null the normalized decimal string (e.g. "1234.56"), or null for empty input
     */
    public static function parse(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        if (str_contains($trimmed, '-')) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid monetary amount', $input));
        }

        $cleaned = preg_replace('/[^\d,.]/', '', $trimmed) ?? '';

        if (preg_match('/\d/', $cleaned) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid monetary amount', $input));
        }

        $lastComma = strrpos($cleaned, ',');
        $lastDot = strrpos($cleaned, '.');

        if ($lastComma === false && $lastDot === false) {
            $normalized = $cleaned;
        } else {
            $decimalPos = max((int) $lastComma, (int) $lastDot);
            $integerPart = preg_replace('/[,.]/', '', substr($cleaned, 0, $decimalPos)) ?? '';
            $fractionPart = preg_replace('/[^\d]/', '', substr($cleaned, $decimalPos + 1)) ?? '';

            if ($integerPart === '') {
                $integerPart = '0';
            }

            $normalized = $fractionPart === '' ? $integerPart : $integerPart . '.' . $fractionPart;
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $normalized) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid monetary amount', $input));
        }

        \assert($normalized !== '', 'A valid normalized monetary amount cannot be empty.');

        return $normalized;
    }
}
