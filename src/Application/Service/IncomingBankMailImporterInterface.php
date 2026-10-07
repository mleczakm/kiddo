<?php

declare(strict_types=1);

namespace App\Application\Service;

interface IncomingBankMailImporterInterface
{
    public function import(string $id, string $subject, string $content, \DateTimeImmutable $receivedAt): bool;
}
