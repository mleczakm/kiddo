<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

final readonly class BankMailWebhookSignatureVerifier
{
    private const int MAX_TIMESTAMP_SKEW_SECONDS = 300;

    public function isValid(
        string $timestamp,
        string $signature,
        string $body,
        #[\SensitiveParameter]
        string $secret,
    ): bool {
        $matches = [];
        if (
            !preg_match('/\A[0-9]{10}\z/', $timestamp) || !preg_match('/\Av1=([a-f0-9]{64})\z/i', $signature, $matches)
        ) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::MAX_TIMESTAMP_SKEW_SECONDS) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp . "\n" . $body, $secret), strtolower($matches[1]));
    }
}
