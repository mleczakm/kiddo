<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Repository\TransferRepositoryInterface;
use App\Application\Service\IncomingBankMailImporterInterface;
use DirectoryTree\ImapEngine\Message;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class ImportTransfersFromMailHandler
{
    public function __construct(
        private IncomingBankMailImporterInterface $importer,
        private MessageBusInterface $messageBus,
        private IncomingNotificationMailQuery $incomingNotificationMailQuery,
        private TransferRepositoryInterface $transferRepository,
        private LoggerInterface $logger,
        private string $mailboxUsername = '',
        #[\SensitiveParameter]
        private string $mailboxPassword = '',
    ) {}

    public function __invoke(ImportTransfersFromMail $_message): void
    {
        if ($this->mailboxUsername === '' || $this->mailboxPassword === '') {
            $this->logger->info(
                'Mailbox credentials missing/invalid: skipping IMAP read, rematching unmatched transfers instead',
            );
            $this->rematchUnmatchedTransfers();

            return;
        }

        /** @var Message $incomingNotification */
        foreach (($this->incomingNotificationMailQuery)() as $incomingNotification) {
            $subject = $incomingNotification->subject() ?? '';
            $content = $incomingNotification->html() ?? $incomingNotification->text() ?? '';
            $id = trim($incomingNotification->messageId() ?? '');
            if ($id === '') {
                $id = 'sha256:' . hash('sha256', $subject . "\n" . $content);
            }

            // Commit the shared receipt/transfer before acknowledging Gmail.
            $this->importer->import($id, $subject, $content, Clock::get()->now());
            $incomingNotification->markSeen();
        }
    }

    private function rematchUnmatchedTransfers(): void
    {
        foreach ($this->transferRepository->findBy(['payment' => null]) as $transfer) {
            $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));
        }
    }
}
