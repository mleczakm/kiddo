<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

final readonly class BankMailTimestampParser
{
    public function parse(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}Z\z/', $value)) {
            return null;
        }

        try {
            $receivedAt = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s.v\\Z', $value, new \DateTimeZone('UTC'));
        } catch (\DateInvalidTimeZoneException) {
            return null;
        }

        return $receivedAt !== false && \DateTimeImmutable::getLastErrors() === false ? $receivedAt : null;
    }
}
