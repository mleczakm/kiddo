<?php

declare(strict_types=1);

namespace App\Application\Calendar;

/**
 * The small amount of RFC 5545 text plumbing shared by LessonCalendarFactory:
 * value escaping, parameter quoting and content-line folding.
 */
final readonly class IcsWriter
{
    /**
     * Escape a TEXT value: backslash, semicolon, comma and newlines.
     */
    public function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $value);
    }

    /**
     * Quote a parameter value (e.g. an ORGANIZER CN) and drop the characters
     * that cannot appear inside quotes.
     */
    public function quoteParam(string $value): string
    {
        return '"' . str_replace(['"', "\r", "\n"], '', $value) . '"';
    }

    /**
     * RFC 5545 §3.1: content lines longer than 75 octets are wrapped with a
     * CRLF + a leading space. Split on character boundaries so multi-byte
     * UTF-8 is never cut in half.
     */
    public function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        $chunk = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($chunk . $char) > 73) {
                $folded .= ($folded === '' ? '' : "\r\n ") . $chunk;
                $chunk = '';
            }
            $chunk .= $char;
        }

        return $folded . ($folded === '' ? '' : "\r\n ") . $chunk;
    }
}
