<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\ExtendSeriesSchedule;
use App\Application\CommandHandler\ExtendSeriesScheduleHandler;
use App\Entity\WorkshopType;
use App\Repository\LessonRepository;
use App\Repository\SeriesRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\SeriesAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class ExtendSeriesScheduleHandlerTest extends KernelTestCase
{
    public function testExtendsWeeklySeriesTwoMonthsAhead(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var SeriesRepository $seriesRepository */
        $seriesRepository = self::getContainer()->get(SeriesRepository::class);
        /** @var LessonRepository $lessonRepository */
        $lessonRepository = self::getContainer()->get(LessonRepository::class);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $lastLessonDate = $now->modify('+1 day'); // ensure future

        $series = SeriesAssembler::new()
            ->withType(WorkshopType::WEEKLY)
            ->assemble();
        $em->persist($series);

        $lastLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($lastLessonDate)->assemble())
            ->withSeries($series)
            ->assemble();
        $lastLesson->setSeries($series);
        $em->persist($lastLesson);

        $em->flush();

        // Sanity: series should have exactly one lesson initially
        $savedSeries = $seriesRepository->find($series->getId());
        self::assertNotNull($savedSeries);
        self::assertCount(1, $savedSeries->lessons);

        /** @var ExtendSeriesScheduleHandler $handler */
        $handler = self::getContainer()->get(ExtendSeriesScheduleHandler::class);
        $handler(new ExtendSeriesSchedule());

        // Reload series and verify new lessons created up to +2 months horizon
        $savedSeries = $seriesRepository->find($series->getId());
        self::assertNotNull($savedSeries);

        $horizon = $now->modify('+2 months');

        // Count lessons at weekly intervals from one week after lastLessonDate up to horizon (inclusive)
        $expectedDates = [];
        $cursor = $lastLessonDate->modify('+1 week');
        while ($cursor <= $horizon) {
            $expectedDates[] = $cursor;
            $cursor = $cursor->modify('+1 week');
        }

        // Verify that for each expected date there is a lesson in the series
        $actualDates = [];
        foreach ($savedSeries->lessons as $l) {
            $actualDates[] = $l->getMetadata()->schedule->format('c');
        }

        foreach ($expectedDates as $d) {
            self::assertContains($d->format('c'), $actualDates, 'Missing lesson for date ' . $d->format('c'));
        }

        // And total count matches original + expected additions (no duplicates)
        self::assertCount(1 + count($expectedDates), $savedSeries->lessons);
    }
}
