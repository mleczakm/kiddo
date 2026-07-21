<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\WorkshopType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\UserInterface\Http\Component\LessonModal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class LessonModalUrlSyncTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testOpenModalDispatchesWorkshopUrlChangeEvent(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::WEEKLY)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Bałaganki')
                    ->withSchedule(new \DateTimeImmutable('2024-02-21 10:30:00'))
                    ->assemble()
            )
            ->assemble();
        $lesson->setSeries($series);
        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'closeUrl' => '/warsztaty?week=2024-02-21',
            ],
            client: $client,
        );

        $component->call('openModal');

        $this->assertComponentDispatchBrowserEvent($component, 'workshop:url-change')
            ->withPayload([
                'url' => '/warsztaty/balaganki?date=2024-02-21&hour=10:30',
            ]);
        $lessonModal = $component->component();
        $this->assertInstanceOf(LessonModal::class, $lessonModal);
        $this->assertTrue($lessonModal->modalOpened);
    }

    public function testCloseModalDispatchesCloseUrl(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::WEEKLY)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Bałaganki')
                    ->withSchedule(new \DateTimeImmutable('2024-02-21 10:30:00'))
                    ->assemble()
            )
            ->assemble();
        $lesson->setSeries($series);
        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'closeUrl' => '/warsztaty?week=2024-02-21',
            ],
            client: $client,
        );

        $component->call('closeModal');

        $this->assertComponentDispatchBrowserEvent($component, 'workshop:url-change')
            ->withPayload([
                'url' => '/warsztaty?week=2024-02-21',
            ]);
        $lessonModal = $component->component();
        $this->assertInstanceOf(LessonModal::class, $lessonModal);
        $this->assertFalse($lessonModal->modalOpened);
    }
}
