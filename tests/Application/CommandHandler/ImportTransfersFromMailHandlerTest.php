<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\SaveTransfer;
use App\Application\CommandHandler\ImportTransfersFromMailHandler;
use App\Application\CommandHandler\IncomingNotificationMailQuery;
use App\Application\Service\AliorMailParser;
use App\Entity\Transfer;
use App\Repository\SettingRepository;
use App\Repository\TransferRepository;
use App\Tests\Util\MessengerFake;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
class ImportTransfersFromMailHandlerTest extends TestCase
{
    public function testFetchProperlyEmailsFromMailbox(): void
    {
        $settingRepository = $this->createMock(SettingRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $transferRepository = $this->createMock(TransferRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        (new ImportTransfersFromMailHandler(
            new AliorMailParser(),
            $messengerFake = new MessengerFake(),
            new FakeQuery(),
            $settingRepository,
            $entityManager,
            $transferRepository,
            $logger,
            mailboxUsername: 'user@example.com',
            mailboxPassword: 'secret',
        ))(new ImportTransfersFromMail());

        self::assertNotEmpty($messengerFake->dispatched);
        self::assertInstanceOf(SaveTransfer::class, $messengerFake->dispatched[0]->getMessage());
    }

    public function testSkipsImapAndRematchesUnmatchedTransfersWhenCredentialsMissing(): void
    {
        $transfer = new Transfer('123', 'Sender', 'WW5J', '60.00', new \DateTimeImmutable());

        $transferRepository = $this->createMock(TransferRepository::class);
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
            new AliorMailParser(),
            $messengerFake = new MessengerFake(),
            $incomingQuery,
            $this->createMock(SettingRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $transferRepository,
            $logger,
            mailboxUsername: '',
            mailboxPassword: '',
        ))(new ImportTransfersFromMail());

        self::assertCount(1, $messengerFake->dispatched);
        $message = $messengerFake->dispatched[0]->getMessage();
        self::assertInstanceOf(MatchPaymentForTransfer::class, $message);
        self::assertSame($transfer, $message->transfer);
    }
}

class FakeQuery implements IncomingNotificationMailQuery
{
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
        $inbox->addMessage(new FakeMessage(uid: 1, flags: [], contents: $emailContent));

        yield from $inbox->messages()->get();
    }
}
