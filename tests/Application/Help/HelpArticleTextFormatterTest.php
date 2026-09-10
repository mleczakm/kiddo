<?php

declare(strict_types=1);

namespace App\Tests\Application\Help;

use App\Application\Help\HelpArticleTextFormatter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HelpArticleTextFormatterTest extends TestCase
{
    private HelpArticleTextFormatter $formatter;

    #[\Override]
    protected function setUp(): void
    {
        $this->formatter = new HelpArticleTextFormatter();
    }

    public function testFlattensHeadingsListsAndLinks(): void
    {
        $html = <<<'HTML'
            <p>Wstęp z odnośnikiem do <a href="/admin/pomoc/role-i-uprawnienia">ról</a>.</p>
            <h2>Jak nadać uprawnienia</h2>
            <ul>
                <li>Wejdź w <a href="/admin/uzytkownicy">Bazę klientów</a></li>
                <li>Zaznacz role</li>
            </ul>
            HTML;

        $text = $this->formatter->format($html);

        static::assertStringNotContainsString('<', $text);
        static::assertStringContainsString('## Jak nadać uprawnienia', $text);
        static::assertStringContainsString('- Zaznacz role', $text);
        static::assertStringContainsString('ról (/admin/pomoc/role-i-uprawnienia)', $text);
        static::assertStringContainsString('Bazę klientów (/admin/uzytkownicy)', $text);
        static::assertStringNotContainsString("\n\n\n", $text);
    }

    public function testDecodesEntitiesAndTrims(): void
    {
        static::assertSame(
            'Zgoda „AI” & reszta',
            $this->formatter->format('  <p>Zgoda &bdquo;AI&rdquo; &amp; reszta</p>  '),
        );
    }
}
