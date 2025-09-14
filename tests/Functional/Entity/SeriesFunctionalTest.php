<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Series;
use App\Repository\SeriesRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class SeriesFunctionalTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testGetFirstAndLastLessonBySchedule(): void
    {
        $series = new Series(new ArrayCollection());
        $this->em->persist($series);

        $now = new \DateTimeImmutable('now');
        $lEarly = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()->withTitle('Early')->withSchedule($now->modify('-2 days'))->assemble()
            )
            ->assemble();
        $lMid = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()->withTitle('Mid')->withSchedule($now->modify('+1 day'))->assemble()
            )
            ->assemble();
        $lLate = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()->withTitle('Late')->withSchedule($now->modify('+10 days'))->assemble()
            )
            ->assemble();

        $lEarly->setSeries($series);
        $lMid->setSeries($series);
        $lLate->setSeries($series);

        $this->em->persist($lEarly);
        $this->em->persist($lMid);
        $this->em->persist($lLate);
        $this->em->flush();
        $this->em->clear();

        /** @var SeriesRepository $repo */
        $repo = self::getContainer()->get(SeriesRepository::class);
        $reloaded = $repo->find($series->getId()) ?? throw new \LogicException('Series not found');

        $first = $reloaded->getFirstLesson();
        $last = $reloaded->getLastLesson();

        self::assertSame(
            $lEarly->getMetadata()
                ->schedule->format('Y-m-d'),
            $first->getMetadata()
                ->schedule->format('Y-m-d')
        );
        self::assertSame(
            $lLate->getMetadata()
                ->schedule->format('Y-m-d'),
            $last->getMetadata()
                ->schedule->format('Y-m-d')
        );
    }

    public function testGetFirstOrLastLessonThrowsWhenEmpty(): void
    {
        $series = new Series(new ArrayCollection());
        $this->em->persist($series);
        $this->em->flush();
        $this->em->clear();

        /** @var SeriesRepository $repo */
        $repo = self::getContainer()->get(SeriesRepository::class);
        $reloaded = $repo->find($series->getId()) ?? throw new \LogicException('Series not found');

        $this->expectException(\LogicException::class);
        $reloaded->getFirstLesson();
    }
}
