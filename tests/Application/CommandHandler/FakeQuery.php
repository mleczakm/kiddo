<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\CommandHandler\IncomingNotificationMailQuery;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;

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
                'password' => bin2hex(random_bytes(16)),
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
