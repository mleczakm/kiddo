<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\SaveTransfer;
use App\Application\Service\TransferNotificationMailParserInterface;
use App\Entity\Setting;
use App\Entity\Transfer;
use App\Repository\SettingRepository;
use App\Repository\TransferRepository;
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
        private SettingRepository $settingRepository,
        private EntityManagerInterface $entityManager,
        private TransferRepository $transferRepository,
        private LoggerInterface $logger,
        private string $mailboxUsername = '',
        private string $mailboxPassword = '',
    ) {}

    public function __invoke(ImportTransfersFromMail $message): void
    {
        if ($this->mailboxUsername === '' || $this->mailboxPassword === '') {
            $this->logger->info(
                'Mailbox credentials missing/invalid: skipping IMAP read, rematching unmatched transfers instead',
            );
            $this->rematchUnmatchedTransfers();

            return;
        }

        $transfers = [];
        /** @var Message $incomingNotification */
        foreach (($this->incomingNotificationMailQuery)() as $incomingNotification) {
            if (str_starts_with($incomingNotification->subject() ?: '', 'Uznanie rachunku')) {
                $parsed = $this->mailParser->fromMailSubjectAndContent(
                    $incomingNotification->subject() ?: '',
                    $incomingNotification->html() ?: ',',
                );
                if ($parsed) {
                    $transfers[] = new Transfer(
                        $parsed->accountNumber,
                        $parsed->sender,
                        $parsed->title,
                        $parsed->amount,
                        Clock::get()->now(),
                    );
                }
            }
            $incomingNotification->markSeen();
        }
        foreach ($transfers as $transfer) {
            $this->messageBus->dispatch(new SaveTransfer($transfer));
        }

        if (count($transfers) > 0) {
            $this->updateLastSuccessfulImportDate();
        }
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
