<?php

declare(strict_types=1);

namespace App\Application\Service;

class AliorMailParser implements TransferNotificationMailParserInterface
{
    public function fromMailSubjectAndContent(string $subject, string $content): ?TransferNotificationMailParserResult
    {
        if (! preg_match('/Uznanie rachunku ([0-9.]+) kwotą ([0-9 ,.]+) PLN/u', $subject, $matches)) {
            return null;
        }
        [
            1 => $accountNumber,
        ] = $matches;
        if (! preg_match(
            '/kwotą ([0-9., ]+) PLN.*?Nadawca: (.*?)<br.*?Tytuł zlecenia: (.*?)<br/us',
            $content,
            $matches
        )) {
            return null;
        }

        [
            1 => $amount,
            2 => $sender,
            3 => $title,
        ] = $matches;

        return new TransferNotificationMailParserResult(
            accountNumber: trim($accountNumber),
            sender: trim($sender),
            title: trim($title),
            amount: trim($amount),
        );
    }
}
