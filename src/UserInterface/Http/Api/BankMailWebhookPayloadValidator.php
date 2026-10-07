<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

final readonly class BankMailWebhookPayloadValidator
{
    /** @param array<string, string> $payload */
    public function isValid(array $payload, string $address, string $sender): bool
    {
        return !(
            $payload['id'] === ''
            || strlen($payload['id']) > 255
            || preg_match('/[\r\n\x00-\x1F\x7F]/', $payload['id'])
            || strlen($payload['subject']) > 2000
            || strlen($payload['html']) > 100_000
            || strlen($payload['text']) > 100_000
            || strlen($payload['mail_from']) > 320
            || strtolower(trim($payload['recipient'])) !== strtolower(trim($address))
            || strtolower(trim($payload['mail_from'])) !== strtolower(trim($sender))
        );
    }
}
