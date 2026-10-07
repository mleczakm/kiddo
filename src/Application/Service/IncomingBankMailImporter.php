<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\SaveTransfer;
use App\Application\Repository\SettingRepositoryInterface;
use App\Entity\Setting;
use App\Entity\Transfer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\MessageBusInterface;

/** Imports one payment notification idempotently, regardless of its delivery source. */
final readonly class IncomingBankMailImporter implements IncomingBankMailImporterInterface
{
    public function __construct(
        private Connection $connection,
        private TransferNotificationMailParserInterface $mailParser,
        private MessageBusInterface $messageBus,
        private SettingRepositoryInterface $settingRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /** @throws \Throwable */
    #[\Override]
    public function import(string $id, string $subject, string $content, \DateTimeImmutable $receivedAt): bool
    {
        return $this->connection->transactional(
            /** @throws \Throwable */
            function () use ($id, $subject, $content, $receivedAt): bool {
                $inserted = $this->connection->executeStatement(
                    <<<'SQL'
                        INSERT INTO bank_mail_receipt (id, received_at, status)
                        VALUES (:id, :receivedAt, 'processing')
                        ON CONFLICT (id) DO NOTHING
                        SQL,
                    ['id' => $id, 'receivedAt' => $receivedAt->format('Y-m-d H:i:s')],
                );

                if ($inserted === 0) {
                    return false;
                }

                // Transfers imported before this receipt table was introduced must
                // also be recognized during the Gmail/Worker overlap.
                if ($this->connection->fetchOne('SELECT 1 FROM transfer WHERE message_id = :id', ['id' => $id])) {
                    $this->setStatus($id, 'duplicate');

                    return false;
                }

                $parsed = str_starts_with($subject, 'Uznanie rachunku')
                    ? $this->mailParser->fromMailSubjectAndContent($subject, $content)
                    : null;

                if ($parsed === null) {
                    $this->setStatus($id, 'unrecognized');
                    return true;
                }

                $transfer = new Transfer(
                    $parsed->accountNumber,
                    $parsed->sender,
                    $parsed->title,
                    $parsed->amount,
                    $receivedAt,
                );
                $transfer->setMessageId($id);
                $this->messageBus->dispatch(new SaveTransfer($transfer));
                $this->setStatus($id, 'imported');
                $this->updateLastSuccessfulImportDate();

                return true;
            },
        );
    }

    /** @throws \Throwable */
    private function setStatus(string $id, string $status): void
    {
        $this->connection->executeStatement('UPDATE bank_mail_receipt SET status = :status WHERE id = :id', [
            'id' => $id,
            'status' => $status,
        ]);
    }

    /** @throws \Throwable */
    private function updateLastSuccessfulImportDate(): void
    {
        $setting = $this->settingRepository->findOneByKey('last_successful_transfer_import');
        if ($setting === null) {
            $setting = new Setting();
            $setting->setKey('last_successful_transfer_import');
        }

        $setting->setContent(['date' => Clock::get()->now()->format('Y-m-d H:i:s')]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();
    }
}
