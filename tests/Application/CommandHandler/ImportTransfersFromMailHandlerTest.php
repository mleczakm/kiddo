<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\CommandHandler\ImportTransfersFromMailHandler;
use App\Application\Service\AliorMailParser;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class ImportTransfersFromMailHandlerTest extends TestCase
{
    private FakeMailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fake mailbox for testing
        $this->mailbox = new FakeMailbox(
            config: [
                'host' => 'imap.example.com',
                'port' => 993,
            ],
            folders: [
                $fakeFolder = new FakeFolder(
                    path: 'inbox',
                    messages: [
                        new FakeMessage(
                            uid: 1,
                            flags: ['\\Seen'],
                            contents: 'From: powiadomienia@alior.pl\r\nTo: recipient@example.com\r\nSubject: Uznanie rachunku 91...6978 kwotą 50,00 PLN\r\n\r\n<html><br/>
Uprzejmie informujemy, =C5=BCe rachunek 91...6978 zosta=C5=82 uznany kwot=
=C4=85 50,00 PLN.<br/>
Nadawca: MLECZKO MICHA=C5=81<br/>
Tytu=C5=82 zlecenia: X2el<br/>
Saldo rachunku po operacji: 100,00 PLN
<br/>
<br/>
<br/>
<br/>
Z powa=C5=BCaniem<br/>
Zesp=C3=B3=C5=82 Alior Bank<br/>
<br/>
Uwaga:<br/>
Wiadomo=C5=9B=C4=87 zosta=C5=82a wygenerowana na pro=C5=9Bb=C4=99 u=C5=BCyt=
kownika systemu bankowo=C5=9Bci internetowej i przes=C5=82ana na adres, kt=
=C3=B3ry wskaza=C5=82. Prosimy na ni=C4=85 nie odpowiada=C4=87. W przypadku=
 pyta=C5=84 lub w=C4=85tpliwo=C5=9Bci prosimy o kontakt:
<ol><li>przez formularz kontaktowy, kt=C3=B3ry znajduje si=C4=99 na stronie=
 internetowej Alior Banku, w zak=C5=82adce =E2=80=9EKontakt=E2=80=9D lub</l=
i>
<li>pod numerem 19 502 (z zagranicy +48 12 19 502). Op=C5=82ata za po=C5=82=
=C4=85czenie jest zgodna z cennikiem operatora.</li></ol>
<br/>
</html>'
                        ),
                    ]
                ),
            ]
        );
    }

    public function testFetchProperlyEmailsFromMailbox(): void
    {
        $this->markTestSkipped('Skip until accessing query on FakeFolderis fixed');
        $transfers = new ImportTransfersFromMailHandler(
            $this->mailbox,
            new AliorMailParser(),
            $this->createMock(MessageBusInterface::class),
        )(new ImportTransfersFromMail());
        self::assertNotEmpty($transfers);
    }
}
