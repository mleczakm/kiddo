<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use PHPUnit\Framework\Attributes\Group;
use App\Application\Service\AliorMailParser;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class AliorMailParserTest extends TestCase
{
    public function testParse(): void
    {
        $subject = 'Uznanie rachunku 91...6978 kwotą 50,00 PLN';
        $mailContent = <<<HTML
                    <html><br/>
            Uprzejmie informujemy, że rachunek 91...6978 został uznany kwotą 50,00 PLN.<br/>
            Nadawca: JAKIŚ RANDOM<br/>
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
            <ol><li>przez formularz kontaktowy, który znajduje się na stronie internetowej Alior Banku, w zakładce „Kontakt” lub</li>
            <li>pod numerem 19 502 (z zagranicy +48 12 19 502). Opłata za połączenie jest zgodna z cennikiem operatora.</li></ol>
            <br/>
            </html>
            HTML;
        $parser = new AliorMailParser();
        $result = $parser->fromMailSubjectAndContent($subject, $mailContent);

        self::assertNotNull($result);
        self::assertEquals('91...6978', $result->accountNumber);
        self::assertEquals('JAKIŚ RANDOM', $result->sender);
        self::assertEquals('X2el', $result->title);
        self::assertEquals('50,00', $result->amount);
    }
}
