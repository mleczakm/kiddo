<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Query;

use App\Infrastructure\Doctrine\Query\DoctrineTodayLessonsQuery;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class DoctrineTodayLessonsQueryTest extends KernelTestCase
{
    public function testReturnsLessonsForGivenDate(): void
    {
        $date = new DateTimeImmutable('2025-07-09 10:00:00');
        $otherDate = new DateTimeImmutable('2025-07-10 10:00:00');

        /** @var EntityManagerInterface $em */
        $registry = self::getContainer()->get('doctrine');
        static::assertInstanceOf(\Doctrine\Persistence\ManagerRegistry::class, $registry);
        $em = $registry->getManager();

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

        $em->persist($lesson1);
        $em->persist($lesson2);
        $em->persist($lessonOther);
        $em->flush();

        $query = self::getContainer()->get(DoctrineTodayLessonsQuery::class);
        static::assertInstanceOf(DoctrineTodayLessonsQuery::class, $query);
        $results = $query->forDate($date);

        static::assertCount(2, $results);
        static::assertContains($lesson1, $results);
        static::assertContains($lesson2, $results);
        static::assertNotContains($lessonOther, $results);
    }
}
