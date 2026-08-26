<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Repository;

use App\Entity\Series;
use App\Entity\WorkshopType;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\Tests\Assembler\AgeRangeAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class LessonRepositoryTest extends KernelTestCase
{
    public function testFindByDateReturnsLessonsForGivenDate(): void
    {
        $date = new DateTimeImmutable('2025-07-09 10:00:00');
        $otherDate = new DateTimeImmutable('2025-07-10 10:00:00');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();

        $lesson1 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($date)
            ->assemble();
        $lesson2 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($date->setTime(15, 0))
            ->assemble();
        $lessonOther = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($otherDate)
            ->assemble();
        $lessonOther2 = LessonAssembler::new()
            ->withStatus('cancelled')
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($date)
            ->assemble();
        $hiddenLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($date)
            ->withVisible(false)
            ->assemble();
        $hiddenSeries = new Series(new ArrayCollection(), WorkshopType::WEEKLY, visible: false);
        $hiddenBySeries = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($date)
            ->assemble();
        $hiddenBySeries->setSeries($hiddenSeries);

        $em->persist($lesson1);
        $em->persist($lesson2);
        $em->persist($lessonOther);
        $em->persist($hiddenLesson);
        $em->persist($hiddenSeries);
        $em->persist($hiddenBySeries);
        $em->persist($lessonOther2);
        $em->flush();

        /** @var LessonRepository $repo */
        $repo = self::getContainer()->get(LessonRepository::class);
        $results = $repo->findActiveByDate($date);

        static::assertCount(2, $results);
        static::assertContains($lesson1, $results);
        static::assertContains($lesson2, $results);
        static::assertNotContains($lessonOther, $results);
        static::assertNotContains($lessonOther2, $results);
    }

    public function testFindByFilters(): void
    {
        $date = new DateTimeImmutable('2025-07-09 10:00:00');
        $otherDate = new DateTimeImmutable('2025-08-10 10:00:00');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();

        $lesson1 = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()->withAgeRange(
                    AgeRangeAssembler::new()->withMin(1)->withMax(2)->assemble(),
                )->assemble(),
            )
            ->withSchedule($date)
            ->withTitle('ooooo')
            ->assemble();
        $lesson2 = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()->withAgeRange(
                    AgeRangeAssembler::new()->withMin(0)->withMax(1)->assemble(),
                )->assemble(),
            )
            ->withSchedule($date->setTime(15, 0))
            ->assemble();
        $lessonOther = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($otherDate)
            ->assemble();

        $em->persist($lesson1);
        $em->persist($lesson2);
        $em->persist($lessonOther);
        $em->flush();

        /** @var LessonRepository $repo */
        $repo = self::getContainer()->get(LessonRepository::class);
        static::assertCount(2, $repo->findByFilters(null, null, week: $date->format('Y-m-d')));
        static::assertCount(1, $repo->findByFilters(null, 0, week: $date->format('Y-m-d')));
        static::assertCount(2, $repo->findByFilters(null, 1, week: $date->format('Y-m-d')));
        static::assertEmpty($repo->findByFilters(null, 99, week: $date->format('Y-m-d')));
        static::assertCount(1, $repo->findByFilters(null, 2, week: $date->format('Y-m-d')));
        static::assertCount(1, $repo->findByFilters('OOOOO', null, week: $date->format('Y-m-d')));
    }

    public function testFindUpcoming(): void
    {
        $date = new DateTimeImmutable('+2 day')->setTime(12, 12);
        $otherDate = new DateTimeImmutable('2025-08-10 10:00:00');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();

        $lesson1 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($date)
            ->assemble();
        $lesson2 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($date->setTime(12, 13))
            ->assemble();
        $lessonOther = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($otherDate)
            ->assemble();

        $em->persist($lesson1);
        $em->persist($lesson2);
        $em->persist($lessonOther);
        $em->flush();

        /** @var LessonRepository $repo */
        $repo = self::getContainer()->get(LessonRepository::class);
        static::assertCount(2, $repo->findUpcoming($now = new DateTimeImmutable(), 10));
        static::assertEquals($lesson1, $repo->findUpcoming($now, 10)[0]);
        static::assertEquals($lesson2, $repo->findUpcoming($now, 10)[1]);
    }
}
