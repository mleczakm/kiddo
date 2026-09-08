<?php

declare(strict_types=1);

namespace App\Application\Calendar;

use App\Application\Service\OrganizationDetails;
use App\Application\Service\OrganizationDetailsProvider;
use App\Entity\Lesson;

/**
 * Builds calendar payloads (an .ics file, a Google Calendar "add event" link)
 * for the lessons a customer has booked, so booking-confirmation emails and
 * payment screens can offer an "add to calendar" action. Every event carries
 * the full workshop blurb plus the studio's address and contact details.
 */
final readonly class LessonCalendarFactory
{
    private const string PRODID = '-//Warsztatownia Sensoryczna//Panel//PL';
    private const string UID_DOMAIN = 'warsztatowniasensoryczna.pl';
    private const string SITE_URL = 'https://warsztatowniasensoryczna.pl';

    public function __construct(
        private OrganizationDetailsProvider $organizationDetails,
        private IcsWriter $ics,
    ) {}

    /**
     * @param iterable<Lesson> $lessons
     *
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function icsForLessons(iterable $lessons): string
    {
        return $this->calendar($lessons, []);
    }

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function icsForLesson(Lesson $lesson): string
    {
        return $this->icsForLessons([$lesson]);
    }

    /**
     * A calendar meant to be *subscribed to* (Google/Apple "add by URL"):
     * carries a display name and a refresh hint, and emits cancelled lessons
     * as `STATUS:CANCELLED` VEVENTs so subscribers drop them on the next poll.
     *
     * @param iterable<Lesson> $lessons
     *
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function calendarFeed(iterable $lessons, string $calendarName): string
    {
        return $this->calendar($lessons, [
            'NAME:' . $this->ics->escape($calendarName),
            'X-WR-CALNAME:' . $this->ics->escape($calendarName),
            'REFRESH-INTERVAL;VALUE=DURATION:PT12H',
            'X-PUBLISHED-TTL:PT12H',
        ]);
    }

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function googleCalendarUrl(Lesson $lesson): string
    {
        $utc = new \DateTimeZone('UTC');
        $metadata = $lesson->getMetadata();
        $start = $lesson->schedule->setTimezone($utc);
        $end = $this->endOf($lesson)->setTimezone($utc);

        return 'https://calendar.google.com/calendar/render?'
        . http_build_query([
            'action' => 'TEMPLATE',
            'text' => $metadata->title,
            'dates' => $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
            'details' => $this->describe($lesson),
            'location' => $this->organizationDetails->get()->addressLine(),
        ]);
    }

    /**
     * @param iterable<Lesson> $lessons
     * @param list<string>     $extraHeaders
     *
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    private function calendar(iterable $lessons, array $extraHeaders): string
    {
        $utc = new \DateTimeZone('UTC');
        $organization = $this->organizationDetails->get();
        $stamp = new \DateTimeImmutable('now', $utc);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            ...$extraHeaders,
        ];

        foreach ($lessons as $lesson) {
            foreach ($this->vevent($lesson, $organization, $stamp, $utc) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->ics->fold(...), $lines)) . "\r\n";
    }

    /**
     * @return list<string>
     *
     * @throws \DateMalformedStringException
     */
    private function vevent(
        Lesson $lesson,
        OrganizationDetails $organization,
        \DateTimeImmutable $stamp,
        \DateTimeZone $utc,
    ): array {
        $metadata = $lesson->getMetadata();
        $start = $lesson->schedule->setTimezone($utc);
        $end = $this->endOf($lesson)->setTimezone($utc);

        $lines = [
            'BEGIN:VEVENT',
            'UID:lesson-' . (string) $lesson->getId() . '@' . self::UID_DOMAIN,
            'DTSTAMP:' . $stamp->format('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis\Z'),
            'DTEND:' . $end->format('Ymd\THis\Z'),
            'SUMMARY:' . $this->ics->escape($metadata->title),
            'DESCRIPTION:' . $this->ics->escape($this->describe($lesson)),
            'LOCATION:' . $this->ics->escape($organization->addressLine()),
            'ORGANIZER;CN=' . $this->ics->quoteParam($organization->name) . ':mailto:' . $organization->email,
            'CONTACT:'
                . $this->ics->escape(sprintf(
                    '%s, tel. %s, %s',
                    $organization->name,
                    $organization->phone,
                    $organization->email,
                )),
            'STATUS:' . ($lesson->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
        ];

        if ($metadata->slug !== null && $metadata->slug !== '') {
            $lines[] = 'URL:' . self::SITE_URL . '/warsztaty/' . $metadata->slug;
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * Human-readable event body: the workshop blurb followed by the studio's
     * address and contact details. Plain text with real newlines — escape()
     * turns them into the literal `\n` an .ics DESCRIPTION needs, and
     * http_build_query() encodes them for the Google Calendar link.
     */
    private function describe(Lesson $lesson): string
    {
        $organization = $this->organizationDetails->get();
        $metadata = $lesson->getMetadata();

        $blurb = array_filter([trim($metadata->lead), $this->plainText($metadata->description)]);
        $blurb[] = implode("\n", [
            'Organizator: ' . $organization->name,
            'Adres: ' . $organization->addressLine(),
            'Telefon: ' . $organization->phone,
            'E-mail: ' . $organization->email,
        ]);

        return implode("\n\n", $blurb);
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function endOf(Lesson $lesson): \DateTimeImmutable
    {
        return $lesson->schedule->modify('+' . $lesson->getMetadata()->duration . ' minutes');
    }

    private function plainText(string $value): string
    {
        $withBreaks = str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $value);
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/[ \t]*\n\s*\n\s*/', "\n\n", $text) ?? $text);
    }
}
