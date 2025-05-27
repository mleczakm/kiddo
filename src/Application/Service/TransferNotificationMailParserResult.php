<?php

declare(strict_types=1);

namespace App\Application\Service;

readonly class TransferNotificationMailParserResult
{
    /**'accountNumber' => $accountNumber,
     * 'sender' => $sender,
     * 'title' => $title,
     * 'amount' => $amount,*/
    public function __construct(
        public string $accountNumber,
        public string $sender,
        public string $title,
        public string $amount,
    ) {}
}
