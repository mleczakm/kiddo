<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Repository\LessonRepository;
use App\Tests\Assembler\AgeRangeAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonRepositoryTest extends KernelTestCase
{
    public function testFindByDateReturnsLessonsForGivenDate(): void
    {
        $date = new DateTimeImmutable('2025-07-09 10:00:00');
        $otherDate = new DateTimeImmutable('2025-07-10 10:00:00');

        $em = self::getContainer()->get('doctrine')->getManager();

        $lesson1 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($date)->assemble())
            ->assemble();
        $lesson2 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($date->setTime(15, 0))->assemble())
            ->assemble();
        $lessonOther = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($otherDate)->assemble())
            ->assemble();
        $lessonOther2 = LessonAssembler::new()
            ->withStatus('cancelled')
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($date)->assemble())
            ->assemble();

        $em->persist($lesson1);
        $em->persist($lesson2);
        $em->persist($lessonOther);
        $em->persist($lessonOther2);
        $em->flush();

        /** @var LessonRepository $repo */
        $repo = self::getContainer()->get(LessonRepository::class);
        $results = $repo->findActiveByDate($date);

        $this->assertCount(2, $results);
        $this->assertContains($lesson1, $results);
        $this->assertContains($lesson2, $results);
        $this->assertNotContains($lessonOther, $results);
        $this->assertNotContains($lessonOther2, $results);
    }

    public function testFindByFilters(): void
    {
        $date = new DateTimeImmutable('2025-07-09 10:00:00');
        $otherDate = new DateTimeImmutable('2025-08-10 10:00:00');

        $em = self::getContainer()->get('doctrine')->getManager();

        $lesson1 = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withAgeRange(AgeRangeAssembler::new()->withMin(1)->withMax(2)->assemble())
                    ->withSchedule($date)
                    ->assemble()
            )
            ->withTitle('ooooo')
            ->assemble();
        $lesson2 = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()->withSchedule($date->setTime(15, 0))
                    ->withAgeRange(AgeRangeAssembler::new()->withMin(0)->withMax(1)->assemble())
                    ->assemble()
            )
            ->assemble();
        $lessonOther = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new() ->withSchedule($otherDate) ->assemble())
            ->assemble();

        $em->persist($lesson1);
        $em->persist($lesson2);
        $em->persist($lessonOther);
        $em->flush();

        /** @var LessonRepository $repo */
        $repo = self::getContainer()->get(LessonRepository::class);
        $this->assertCount(2, $repo->findByFilters(null, null, week: $date->format('Y-m-d')));
        $this->assertCount(1, $repo->findByFilters(null, 0, week: $date->format('Y-m-d')));
        $this->assertCount(2, $repo->findByFilters(null, 1, week: $date->format('Y-m-d')));
        $this->assertEmpty($repo->findByFilters(null, 99, week: $date->format('Y-m-d')));
        $this->assertCount(1, $repo->findByFilters(null, 2, week: $date->format('Y-m-d')));
        $this->assertCount(1, $repo->findByFilters('OOOOO', null, week: $date->format('Y-m-d')));
    }

    public function testFindUpcoming(): void
    {
        $date = new DateTimeImmutable('+2 day')->setTime(12, 12);
        $otherDate = new DateTimeImmutable('2025-08-10 10:00:00');

        $em = self::getContainer()->get('doctrine')->getManager();

        $lesson1 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($date)->assemble())
            ->assemble();
        $lesson2 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($date->setTime(12, 13))->assemble())
            ->assemble();
        $lessonOther = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($otherDate)->assemble())
            ->assemble();

        $em->persist($lesson1);
        $em->persist($lesson2);
        $em->persist($lessonOther);
        $em->flush();

        /** @var LessonRepository $repo */
        $repo = self::getContainer()->get(LessonRepository::class);
        $this->assertCount(2, $repo->findUpcoming($now = new DateTimeImmutable(), 10));
        $this->assertEquals($lesson1, $repo->findUpcoming($now, 10)[0]);
        $this->assertEquals($lesson2, $repo->findUpcoming($now, 10)[1]);
    }
}
