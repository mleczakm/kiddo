<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\MatchPaymentForTransfer;
use App\Application\CommandHandler\ImportTransfersFromMailHandler;
use App\Application\CommandHandler\IncomingNotificationMailQuery;
use App\Application\Repository\TransferRepositoryInterface;
use App\Application\Service\IncomingBankMailImporterInterface;
use App\Entity\Transfer;
use App\Tests\Util\MessengerFake;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
class ImportTransfersFromMailHandlerTest extends TestCase
{
    public function testFetchProperlyEmailsFromMailbox(): void
    {
        $query = new FakeQuery();
        $importer = $this->createMock(IncomingBankMailImporterInterface::class);
        $importer
            ->expects($this->once())
            ->method('import')
            ->willReturnCallback(static function (
                string $id,
                string $subject,
                string $content,
                \DateTimeImmutable $receivedAt,
            ): bool {
                static::assertSame('test-uznanie-1@alior.pl', $id);
                static::assertStringStartsWith('Uznanie rachunku', $subject);
                static::assertStringContainsString('Tytuł zlecenia: X2el', $content);
                static::assertInstanceOf(\DateTimeImmutable::class, $receivedAt);

                return true;
            });
        $messengerFake = new MessengerFake();
        $this->makeHandler($messengerFake, $query, $this->createMock(TransferRepositoryInterface::class), $importer)(
            new ImportTransfersFromMail(),
        );
        static::assertEmpty($messengerFake->dispatched);
        static::assertTrue($query->message?->isSeen(), 'Gmail is acknowledged after the importer returns');
    }

    public function testDoesNotMarkEmailSeenWhenSharedImportFails(): void
    {
        $query = new FakeQuery();
        $importer = $this->createMock(IncomingBankMailImporterInterface::class);
        $importer
            ->expects($this->once())
            ->method('import')
            ->willThrowException(new \RuntimeException('DB unavailable'));

        try {
            $this->makeHandler(
                new MessengerFake(),
                $query,
                $this->createMock(TransferRepositoryInterface::class),
                $importer,
            )(new ImportTransfersFromMail());
            static::fail('The import failure should be propagated so the scheduler retries it');
        } catch (\RuntimeException $exception) {
            static::assertSame('DB unavailable', $exception->getMessage());
        }

        static::assertFalse($query->message?->isSeen(), 'A failed import remains unread in Gmail');
    }

    private function makeHandler(
        MessengerFake $messengerFake,
        IncomingNotificationMailQuery $incomingQuery,
        TransferRepositoryInterface $transferRepository,
        IncomingBankMailImporterInterface $importer,
    ): ImportTransfersFromMailHandler {
        return new ImportTransfersFromMailHandler(
            $importer,
            $messengerFake,
            $incomingQuery,
            $transferRepository,
            $this->createMock(LoggerInterface::class),
            mailboxUsername: 'user@example.com',
            mailboxPassword: 'secret',
        );
    }

    public function testSkipsImapAndRematchesUnmatchedTransfersWhenCredentialsMissing(): void
    {
        $transfer = new Transfer('123', 'Sender', 'WW5J', '60.00', new \DateTimeImmutable());

        $transferRepository = $this->createMock(TransferRepositoryInterface::class);
        $transferRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([
                'payment' => null,
            ])
            ->willReturn([$transfer]);

        $incomingQuery = $this->createMock(IncomingNotificationMailQuery::class);
        $incomingQuery->expects($this->never())->method('__invoke');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Mailbox credentials missing/invalid: skipping IMAP read, rematching unmatched transfers instead');

        (new ImportTransfersFromMailHandler(
            $this->createMock(IncomingBankMailImporterInterface::class),
            $messengerFake = new MessengerFake(),
            $incomingQuery,
            $transferRepository,
            $logger,
            mailboxUsername: '',
            mailboxPassword: '',
        ))(new ImportTransfersFromMail());

        static::assertCount(1, $messengerFake->dispatched);
        $message = $messengerFake->dispatched[0]->getMessage();
        static::assertInstanceOf(MatchPaymentForTransfer::class, $message);
        static::assertSame($transfer, $message->transfer);
    }
}

class FakeQuery implements IncomingNotificationMailQuery
{
    public ?FakeMessage $message = null;

    #[\Override]
    public function __invoke(): iterable
    {
        $mailbox = new FakeMailbox(
            // Configuration
            config: [
                'host' => 'imap.example.com',
                'port' => 993,
                'username' => 'test@example.com',
                'password' => 'password',
                'encryption' => 'ssl',
            ],
            // Folders
            folders: [new FakeFolder('inbox'), new FakeFolder('sent'), new FakeFolder('trash')],
            // Capabilities
            capabilities: ['IMAP4rev1', 'IDLE', 'UIDPLUS'],
        );

        $emailContent = <<<EMAIL
            From: powiadomienia@alior.pl
            To: recipient@example.com
            Subject: Uznanie rachunku 91...1234 kwotą 50,00 PLN
            Message-ID: <test-uznanie-1@alior.pl>
            Content-Type: text/html; charset=utf-8

            <html><br/>
            Uprzejmie informujemy, że rachunek 91...1234 został uznany kwotą 50,00 PLN.<br/>
            Nadawca: SOME ANON<br/>
            Tytuł zlecenia: X2el<br/>
            Saldo rachunku po operacji: 100,00 PLN
            <br/>
            <br/>
            <br/>
            <br/>
            Z poważaniem<br/>
            Zespół Alior Bank<br/>
            <br/>
            Uwaga:<br/>
            Wiadomość została wygenerowana na prośbę użytkownika systemu bankowości internetowej i przesłana na adres, który wskazał. Prosimy na nią nie odpowiadać. W przypadku pytań lub wątpliwości prosimy o kontakt:
            <ol><li>przez formularz kontaktowy, który znajduje się na stronie internetowej Alior Banku, w zakładce "Kontakt" lub</li>
            <li>pod numerem 19 502 (z zagranicy +48 12 19 502). Opłata za połączenie jest zgodna z cennikiem operatora.</li></ol>
            <br/>
            </html>
            EMAIL;

        /** @var FakeFolder $inbox */
        $inbox = $mailbox->inbox();
        $email = new FakeMessage(uid: 1, flags: [], contents: $emailContent);
        $this->message = $email;
        $inbox->addMessage($email);

        yield from $inbox->messages()->get();
    }
}
