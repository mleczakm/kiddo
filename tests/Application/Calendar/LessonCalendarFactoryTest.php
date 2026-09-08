<?php

declare(strict_types=1);

namespace App\Tests\Application\Calendar;

use App\Application\Calendar\LessonCalendarFactory;
use App\Tests\Assembler\LessonAssembler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class LessonCalendarFactoryTest extends KernelTestCase
{
    private function factory(): LessonCalendarFactory
    {
        $factory = self::getContainer()->get(LessonCalendarFactory::class);
        static::assertInstanceOf(LessonCalendarFactory::class, $factory);

        return $factory;
    }

    public function testIcsForLessonsBuildsOneVeventPerLesson(): void
    {
        $first = LessonAssembler::new()
            ->withTitle('Sensoryka, poziom 1')
            ->withSchedule(new DateTimeImmutable('2026-09-10 17:30:00'))
            ->withDuration(60)
            ->assemble();
        $second = LessonAssembler::new()
            ->withTitle('Malowanie')
            ->withSchedule(new DateTimeImmutable('2026-09-17 17:30:00'))
            ->withDuration(90)
            ->assemble();

        $ics = $this->factory()->icsForLessons([$first, $second]);

        static::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        static::assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        static::assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        static::assertStringContainsString('SUMMARY:Sensoryka\, poziom 1', $ics);
        static::assertStringContainsString('SUMMARY:Malowanie', $ics);
        static::assertStringContainsString(
            'UID:lesson-' . (string) $first->getId() . '@warsztatowniasensoryczna.pl',
            $ics,
        );
        static::assertStringContainsString('LOCATION:Aleja Jana Pawła II 12D\, 05-250 Radzymin', $ics);
        // 17:30 Europe/Warsaw in September (CEST, +02:00) is 15:30 UTC; +60 min end.
        static::assertStringContainsString('DTSTART:20260910T153000Z', $ics);
        static::assertStringContainsString('DTEND:20260910T163000Z', $ics);
    }

    public function testEventsCarryTheBlurbAddressAndContactDetails(): void
    {
        $lesson = LessonAssembler::new()
            ->withTitle('Sensoryka')
            ->withLead('Zajęcia sensoryczne dla maluchów.')
            ->withDescription('<p>Prowadzone przez fizjoterapeutę.</p>')
            ->withSchedule(new DateTimeImmutable('2026-09-10 17:30:00'))
            ->withDuration(60)
            ->assemble();

        $ics = $this->factory()->icsForLesson($lesson);
        // Unfold RFC 5545 continuation lines before inspecting long properties.
        $unfolded = str_replace("\r\n ", '', $ics);

        static::assertStringContainsString('ORGANIZER;CN="Warsztatownia Sensoryczna":mailto:', $unfolded);
        static::assertStringContainsString('URL:https://warsztatowniasensoryczna.pl/warsztaty/sensoryka', $unfolded);
        static::assertStringContainsString('CONTACT:Warsztatownia Sensoryczna\, tel. ', $unfolded);
        static::assertMatchesRegularExpression('/DESCRIPTION:.*Zajęcia sensoryczne dla maluchów\./', $unfolded);
        static::assertMatchesRegularExpression('/DESCRIPTION:.*Prowadzone przez fizjoterapeutę\./', $unfolded);
        static::assertMatchesRegularExpression('/DESCRIPTION:.*Adres: Aleja Jana Pawła II 12D/', $unfolded);
        static::assertMatchesRegularExpression('/DESCRIPTION:.*Telefon: /', $unfolded);

        // Every content line stays within the 75-octet limit once folded.
        foreach (explode("\r\n", $ics) as $line) {
            static::assertLessThanOrEqual(75, strlen($line), "Line too long: {$line}");
        }
    }

    public function testGoogleCalendarUrlCarriesTheEventWindow(): void
    {
        $lesson = LessonAssembler::new()
            ->withTitle('Sensoryka')
            ->withSchedule(new DateTimeImmutable('2026-09-10 17:30:00'))
            ->withDuration(60)
            ->assemble();

        $url = $this->factory()->googleCalendarUrl($lesson);

        static::assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);
        static::assertStringContainsString('action=TEMPLATE', $url);
        static::assertStringContainsString('text=Sensoryka', $url);
        static::assertStringContainsString('dates=20260910T153000Z%2F20260910T163000Z', $url);
    }

    public function testCalendarFeedNamesTheCalendarAndFlagsCancelledLessons(): void
    {
        $active = LessonAssembler::new()
            ->withTitle('Zajęcia aktywne')
            ->withSchedule(new DateTimeImmutable('2026-09-10 17:30:00'))
            ->withDuration(60)
            ->assemble();
        $cancelled = LessonAssembler::new()
            ->withTitle('Zajęcia odwołane')
            ->withStatus('cancelled')
            ->withSchedule(new DateTimeImmutable('2026-09-12 17:30:00'))
            ->withDuration(60)
            ->assemble();

        $ics = $this->factory()->calendarFeed([$active, $cancelled], 'Warsztatownia – wszystkie zajęcia');
        $unfolded = str_replace("\r\n ", '', $ics);

        static::assertStringContainsString('X-WR-CALNAME:Warsztatownia – wszystkie zajęcia', $unfolded);
        static::assertStringContainsString('REFRESH-INTERVAL;VALUE=DURATION:PT12H', $ics);
        static::assertStringContainsString('SUMMARY:Zajęcia aktywne', $unfolded);
        static::assertStringContainsString('SUMMARY:Zajęcia odwołane', $unfolded);
        static::assertSame(1, substr_count($ics, 'STATUS:CANCELLED'));
        static::assertSame(1, substr_count($ics, 'STATUS:CONFIRMED'));
    }
}
