<?php

declare(strict_types=1);

namespace App\Tests\Application\Calendar;

use App\Application\Calendar\IcsWriter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class IcsWriterTest extends TestCase
{
    private IcsWriter $writer;

    #[\Override]
    protected function setUp(): void
    {
        $this->writer = new IcsWriter();
    }

    public function testEscapeBackslashSemicolonCommaAndNewlines(): void
    {
        static::assertSame('a\\\\b\\;c\\,d\\ne', $this->writer->escape("a\\b;c,d\ne"));
        static::assertSame('one\\ntwo', $this->writer->escape("one\r\ntwo"));
    }

    public function testQuoteParamWrapsAndStripsIllegalCharacters(): void
    {
        static::assertSame('"Warsztatownia Sensoryczna"', $this->writer->quoteParam('Warsztatownia Sensoryczna'));
        static::assertSame('"ab"', $this->writer->quoteParam("a\"\r\nb"));
    }

    public function testFoldLeavesShortLinesUntouched(): void
    {
        $line = 'SUMMARY:' . str_repeat('x', 60);

        static::assertSame($line, $this->writer->fold($line));
    }

    public function testFoldWrapsLongLinesWithinSeventyFiveOctets(): void
    {
        $line = 'DESCRIPTION:' . str_repeat('abcdefghij', 30);

        $folded = $this->writer->fold($line);

        static::assertStringContainsString("\r\n ", $folded);
        foreach (explode("\r\n", $folded) as $segment) {
            static::assertLessThanOrEqual(75, strlen($segment));
        }
        // Unfolding restores the original content exactly.
        static::assertSame($line, str_replace("\r\n ", '', $folded));
    }

    public function testFoldKeepsMultibyteCharactersIntact(): void
    {
        $line = 'DESCRIPTION:' . str_repeat('ąćęłńóśźż', 20);

        $folded = $this->writer->fold($line);

        static::assertSame($line, str_replace("\r\n ", '', $folded));
        foreach (explode("\r\n", $folded) as $segment) {
            static::assertLessThanOrEqual(75, strlen($segment));
            static::assertTrue(mb_check_encoding($segment, 'UTF-8'));
        }
    }
}
