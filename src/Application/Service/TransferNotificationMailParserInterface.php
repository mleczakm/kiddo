<?php

declare(strict_types=1);

namespace App\Application\Service;

interface TransferNotificationMailParserInterface
{
    public function fromMailSubjectAndContent(string $subject, string $content): ?TransferNotificationMailParserResult;
}
