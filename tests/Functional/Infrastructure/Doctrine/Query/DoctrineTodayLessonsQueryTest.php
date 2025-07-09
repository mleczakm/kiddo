<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Doctrine\Query;

use App\Infrastructure\Doctrine\Query\DoctrineTodayLessonsQuery;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineTodayLessonsQueryTest extends KernelTestCase
{
    public function testReturnsLessonsForGivenDate(): void
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

        $em->persist($lesson1);
        $em->persist($lesson2);
        $em->persist($lessonOther);
        $em->flush();

        $query = self::getContainer()->get(DoctrineTodayLessonsQuery::class);
        $results = $query->forDate($date);

        $this->assertCount(2, $results);
        $this->assertContains($lesson1, $results);
        $this->assertContains($lesson2, $results);
        $this->assertNotContains($lessonOther, $results);
    }
}
