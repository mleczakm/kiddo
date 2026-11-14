<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Series;
use App\Entity\WorkshopType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\UserInterface\Http\Component\AdminScheduleComponent;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class AdminScheduleWorkshopActionsTest extends AdminScheduleComponentTestCase
{
    public function testOpenAddModalRendersWorkshopEditor(): void
    {
        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client);

        $component->call('openAddModal');

        $html = (string) $component->render();
        static::assertStringContainsString('Kreator Warsztatów', $html);
    }

    public function testStartEditRendersWorkshopEditorPrefilledWithSeriesData(): void
    {
        $weekStart = new \DateTimeImmutable('2025-01-06');
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, []);
        $this->em->persist($series);

        $l1 = $this->createLesson('Editable Series Title', $weekStart->modify('+1 day'));
        $l1->setSeries($series);
        $this->em->persist($l1);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);

        $component->call('startEdit', [
            'seriesId' => (string) $series->getId(),
        ]);

        $html = (string) $component->render();
        static::assertStringContainsString('Edytuj warsztat', $html);
        // Confirms the series id was correctly resolved to a Ulid and its data loaded
        static::assertStringContainsString('Editable Series Title', $html);
    }

    public function testClickingSeriesCanOpenPreviewWithLessonsAndEditAction(): void
    {
        $weekStart = new \DateTimeImmutable('+1 week monday');
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, []);
        $this->em->persist($series);

        $lesson = $this->createLesson('Preview Series Title', $weekStart->modify('+1 day'));
        $lesson->setSeries($series);
        $this->em->persist($lesson);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $component->call('openPreview', [
            'seriesId' => (string) $series->getId(),
        ]);

        $html = (string) $component->render();
        static::assertStringContainsString('Podgląd warsztatu', $html);
        static::assertStringContainsString('Preview Series Title', $html);
        static::assertStringContainsString('Harmonogram i Rezerwacje', $html);
        static::assertStringContainsString('Edytuj', $html);
        static::assertStringContainsString('Zakończ cykl', $html);
    }

    public function testEndSeriesOpensEditorWithTodayAsLastOccurrence(): void
    {
        $weekStart = new \DateTimeImmutable('+1 week monday');
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, []);
        $this->em->persist($series);

        $lesson = $this->createLesson('Series To End', $weekStart->modify('+1 day'));
        $lesson->setSeries($series);
        $this->em->persist($lesson);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $component->call('endSeries', [
            'seriesId' => (string) $series->getId(),
        ]);

        $html = (string) $component->render();
        static::assertStringContainsString('Kończysz cykl dzisiaj', $html);
        static::assertStringContainsString(new \DateTimeImmutable('today')->format('Y-m-d'), $html);
    }

    public function testToggleLessonStatusDisablesAndEnables(): void
    {
        $weekStart = new \DateTimeImmutable('2025-02-03');

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($weekStart->modify('+1 day'))
            ->withTitle('Togglable Lesson')
            ->withStatus('active')
            ->assemble();

        $this->em->persist($lesson);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);

        // Deactivate
        $component->call('toggleLessonStatus', [
            'lessonId' => (string) $lesson->getId(),
        ]);
        $this->em->clear();

        $reloaded = $this->em->getRepository($lesson::class)->find($lesson->getId());
        static::assertNotNull($reloaded);
        static::assertSame('cancelled', $reloaded->status);

        // Reactivate
        $component->call('toggleLessonStatus', [
            'lessonId' => (string) $lesson->getId(),
        ]);
        $this->em->clear();
        $reloaded2 = $this->em->getRepository($lesson::class)->find($lesson->getId());
        static::assertNotNull($reloaded2);
        static::assertSame('active', $reloaded2->status);
    }
}
