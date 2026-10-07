<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

final readonly class BankMailWebhookPayloadDecoder
{
    /**
     * @param array<array-key, mixed> $payload
     * @return array<string, string>|null
     */
    public function decode(array $payload): ?array
    {
        foreach (['id', 'received_at', 'recipient', 'mail_from', 'subject', 'html', 'text'] as $field) {
            if (!array_key_exists($field, $payload) || !is_string($payload[$field])) {
                return null;
            }
        }

        return [
            'id' => (string) $payload['id'],
            'received_at' => (string) $payload['received_at'],
            'recipient' => (string) $payload['recipient'],
            'mail_from' => (string) $payload['mail_from'],
            'subject' => (string) $payload['subject'],
            'html' => (string) $payload['html'],
            'text' => (string) $payload['text'],
        ];
    }
}
