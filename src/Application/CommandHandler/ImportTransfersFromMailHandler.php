<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\SaveTransfer;
use App\Application\Service\TransferNotificationMailParserInterface;
use App\Entity\Transfer;
use DirectoryTree\ImapEngine\MailboxInterface;
use DirectoryTree\ImapEngine\Message;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ImportTransfersFromMailHandler
{
    public function __construct(
        private readonly MailboxInterface $mailbox,
        private readonly TransferNotificationMailParserInterface $mailParser,
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function __invoke(ImportTransfersFromMail $message): void
    {
        $transfers = [];
        /** @var Message $incomingNotification */
        foreach ($this->mailbox->inbox()->messages()->from('powiadomienia@alior.pl')->withHeaders()
            ->withBody()
            ->unseen()
            ->get() as $incomingNotification) {

            if (str_starts_with($incomingNotification->subject() ?? '', 'Uznanie rachunku')) {
                $parsed = $this->mailParser->fromMailSubjectAndContent(
                    $incomingNotification->subject() ?? '',
                    $incomingNotification->html() ?? ','
                );
                if ($parsed) {
                    $transfers[] = new Transfer(
                        $parsed->accountNumber,
                        $parsed->sender,
                        $parsed->title,
                        $parsed->amount,
                        Clock::get()->now()
                    );
                }

            }

            $incomingNotification->markSeen();
        }

        foreach ($transfers as $transfer) {
            $this->messageBus->dispatch(new SaveTransfer($transfer));
        }
    }
}
