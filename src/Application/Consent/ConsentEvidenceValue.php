<?php

declare(strict_types=1);

namespace App\Application\Consent;

final class ConsentEvidenceValue
{
    /** @throws \InvalidArgumentException */
    public static function checksum(string $text): string
    {
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Consent acceptance text cannot be empty.');
        }

        return hash('sha256', $text);
    }

    /** @throws \InvalidArgumentException */
    public static function normalizeContext(?string $context): ?string
    {
        $context = $context === null ? null : trim($context);
        if ($context === '') {
            return null;
        }
        if ($context !== null && mb_strlen($context) > 255) {
            throw new \InvalidArgumentException('Consent context cannot exceed 255 characters.');
        }

        return $context;
    }

    /** @throws \InvalidArgumentException */
    public static function assertChecksum(string $checksum, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            throw new \InvalidArgumentException("{$label} checksum must be a lowercase SHA-256 hash.");
        }
    }
}
