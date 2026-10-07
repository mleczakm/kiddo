<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use Symfony\Component\HttpFoundation\Request;

final readonly class BankMailWebhookRequestValidator
{
    private const int MAX_BODY_BYTES = 128 * 1024;

    public function __construct(
        private BankMailWebhookSignatureVerifier $signatureVerifier,
        private BankMailWebhookPayloadDecoder $payloadDecoder,
        private BankMailWebhookPayloadValidator $payloadValidator,
        private BankMailTimestampParser $timestampParser,
    ) {}

    /** @throws BankMailWebhookRequestException */
    public function validate(
        Request $request,
        string $address,
        string $sender,
        #[\SensitiveParameter]
        string $secret,
    ): BankMailWebhookMessage {
        $contentLength = $request->headers->get('Content-Length');
        if ($contentLength !== null && ctype_digit($contentLength) && (int) $contentLength > self::MAX_BODY_BYTES) {
            throw new BankMailWebhookRequestException('Request too large', 413);
        }

        $body = $request->getContent();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new BankMailWebhookRequestException('Request too large', 413);
        }

        if (!$this->signatureVerifier->isValid(
            $request->headers->get('X-Kiddo-Bank-Timestamp') ?? '',
            $request->headers->get('X-Kiddo-Bank-Signature') ?? '',
            $body,
            $secret,
        )) {
            throw new BankMailWebhookRequestException('Invalid signature', 401);
        }

        try {
            $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BankMailWebhookRequestException('Invalid payload', 400);
        }

        if (!is_array($payload)) {
            throw new BankMailWebhookRequestException('Invalid payload', 400);
        }

        $payload = $this->payloadDecoder->decode($payload);
        if ($payload === null || !$this->payloadValidator->isValid($payload, $address, $sender)) {
            throw new BankMailWebhookRequestException('Invalid payload', 400);
        }

        $receivedAt = $this->timestampParser->parse($payload['received_at']);
        if ($receivedAt === null) {
            throw new BankMailWebhookRequestException('Invalid timestamp', 400);
        }

        return new BankMailWebhookMessage(
            $payload['id'],
            $payload['subject'],
            $payload['html'] !== '' ? $payload['html'] : $payload['text'],
            $receivedAt,
        );
    }
}
