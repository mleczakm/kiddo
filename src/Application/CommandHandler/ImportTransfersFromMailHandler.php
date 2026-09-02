<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\SaveTransfer;
use App\Application\Repository\SettingRepositoryInterface;
use App\Application\Repository\TransferRepositoryInterface;
use App\Application\Service\TransferNotificationMailParserInterface;
use App\Entity\Setting;
use App\Entity\Transfer;
use DirectoryTree\ImapEngine\Message;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class ImportTransfersFromMailHandler
{
    public function __construct(
        private TransferNotificationMailParserInterface $mailParser,
        private MessageBusInterface $messageBus,
        private IncomingNotificationMailQuery $incomingNotificationMailQuery,
        private SettingRepositoryInterface $settingRepository,
        private EntityManagerInterface $entityManager,
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

        $imported = 0;
        /** @var Message $incomingNotification */
        foreach (($this->incomingNotificationMailQuery)() as $incomingNotification) {
            $messageId = $incomingNotification->messageId();

            if ($messageId !== null && $this->alreadyImported($messageId)) {
                // Same e-mail already stored on an earlier run - e.g. the process
                // died after persisting but before the IMAP \Seen flag stuck, or
                // the async ImportTransfersFromMail message was retried. Re-set
                // the flag and skip it; one e-mail is never imported twice. The
                // unique index on transfer.message_id is the hard backstop for
                // the narrow race between this check and the insert: it turns a
                // duplicate into a failed run (retried 30s later) rather than a
                // duplicate row.
                $this->logger->info('Skipping bank notification e-mail already imported', [
                    'message_id' => $messageId,
                ]);
                $incomingNotification->markSeen();

                continue;
            }

            $transfer = null;
            if (str_starts_with($incomingNotification->subject() ?: '', 'Uznanie rachunku')) {
                $parsed = $this->mailParser->fromMailSubjectAndContent(
                    $incomingNotification->subject() ?: '',
                    $incomingNotification->html() ?: ',',
                );
                if ($parsed) {
                    $transfer = new Transfer(
                        $parsed->accountNumber,
                        $parsed->sender,
                        $parsed->title,
                        $parsed->amount,
                        Clock::get()->now(),
                    );
                    $transfer->setMessageId($messageId);
                }
            }

            $incomingNotification->markSeen();

            if ($transfer !== null) {
                $this->messageBus->dispatch(new SaveTransfer($transfer));
                ++$imported;
            }
        }

        if ($imported > 0) {
            $this->updateLastSuccessfulImportDate();
        }
    }

    private function alreadyImported(string $messageId): bool
    {
        return $this->transferRepository->findOneBy([
            'messageId' => $messageId,
        ]) !== null;
    }

    private function rematchUnmatchedTransfers(): void
    {
        foreach ($this->transferRepository->findBy([
            'payment' => null,
        ]) as $transfer) {
            $this->messageBus->dispatch(new MatchPaymentForTransfer($transfer));
        }
    }

    private function updateLastSuccessfulImportDate(): void
    {
        $setting = $this->settingRepository->findOneByKey('last_successful_transfer_import');
        if ($setting === null) {
            $setting = new Setting();
            $setting->setKey('last_successful_transfer_import');
        }

        $setting->setContent([
            'date' => Clock::get()->now()->format('Y-m-d H:i:s'),
        ]);

        $this->entityManager->persist($setting);
        $this->entityManager->flush();
    }
}
