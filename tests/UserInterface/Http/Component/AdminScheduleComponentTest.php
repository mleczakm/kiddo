<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\WorkshopType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminScheduleComponent;
use Brick\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Covers the merged Warsztaty (Series) + Zajęcia (Lesson) admin view: a
 * Series is grouped with its own occurrences in the selected week, a Lesson
 * without a Series renders as its own row, and a ROLE_HOST-only user only
 * ever sees what they instruct.
 */
#[Group('functional')]
final class AdminScheduleComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);
    }

    public function testEmptyStateShowsMessage(): void
    {
        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client);
        $html = (string) $component->render();

        static::assertStringContainsString('Harmonogram', $html);
        static::assertStringContainsString('Brak zajęć w wybranym tygodniu', $html);
    }

    public function testDisplaysSeriesWithTicketsPeriodTypeAndStatus(): void
    {
        $weekStart = new \DateTimeImmutable('2025-01-06');

        // Create a Series with two lessons in this week
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, [
            new TicketOption(
                TicketType::ONE_TIME,
                Money::of(50, 'PLN'),
                'Bilet jednorazowy',
                TicketReschedulePolicy::UNLIMITED_24H_BEFORE,
            ),
            new TicketOption(
                TicketType::CARNET_4,
                Money::of(180, 'PLN'),
                'Karnet 4',
                TicketReschedulePolicy::ONETIME_24H_BEFORE,
            ),
        ]);
        $this->em->persist($series);

        $l1 = $this->createLesson('Series L1', $weekStart->modify('+1 day'));
        $l2 = $this->createLesson('Series L2', $weekStart->modify('+3 days'));
        $l1->setSeries($series);
        $l2->setSeries($series);

        $this->em->persist($l1);
        $this->em->persist($l2);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('Harmonogram', $html);
        // Type label (weekly)
        static::assertStringContainsString('Cotygodniowa', $html);
        // Both occurrences render nested under the series
        static::assertStringContainsString($weekStart->modify('+1 day')->format('d.m.Y'), $html);
        static::assertStringContainsString($weekStart->modify('+3 days')->format('d.m.Y'), $html);
        // Status badge Active
        static::assertStringContainsString('Aktywne', $html);
        // Ticket type tokens (by enum value)
        static::assertStringContainsString('jednorazowy', $html);
        static::assertStringContainsString('karnet 4 wejścia', $html);
    }

    public function testLessonWithoutSeriesRendersAsOwnRow(): void
    {
        $weekStart = new \DateTimeImmutable('2025-04-07');

        $standalone = $this->createLesson('Standalone Lesson', $weekStart->modify('+1 day'));
        $this->em->persist($standalone);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('Standalone Lesson', $html);
    }

    public function testCancelledSeriesHiddenByDefault(): void
    {
        $weekStart = new \DateTimeImmutable('2025-05-05');
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, [], 'cancelled');
        $this->em->persist($series);

        $l1 = $this->createLesson('Hidden Series Lesson', $weekStart->modify('+1 day'));
        $l1->setSeries($series);
        $this->em->persist($l1);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringNotContainsString('Hidden Series Lesson', $html);
    }

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

    public function testWeekFilteringOnlyShowsSelectedWeek(): void
    {
        $weekStart = new \DateTimeImmutable('2025-02-10');
        $prevWeek = $weekStart->modify('-7 days');

        $inWeek = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($weekStart->modify('+1 day'))
            ->withTitle('In Week Lesson')
            ->withStatus('active')
            ->assemble();
        $outOfWeek = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($prevWeek->modify('+1 day'))
            ->withTitle('Out Lesson')
            ->withStatus('active')
            ->assemble();

        $this->em->persist($inWeek);
        $this->em->persist($outOfWeek);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('In Week Lesson', $html);
        static::assertStringNotContainsString('Out Lesson', $html);
    }

    public function testHostOnlySeesLessonsTheyInstruct(): void
    {
        $weekStart = new \DateTimeImmutable('2025-03-03');

        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $this->em->persist($host);

        $ownLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($weekStart->modify('+1 day'))
            ->withTitle('Own Lesson')
            ->withStatus('active')
            ->assemble();
        $ownLesson->addInstructor($host);

        $otherLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($weekStart->modify('+2 days'))
            ->withTitle('Other Lesson')
            ->withStatus('active')
            ->assemble();

        $this->em->persist($ownLesson);
        $this->em->persist($otherLesson);
        $this->em->flush();

        $this->client->loginUser($host);

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('Own Lesson', $html);
        static::assertStringNotContainsString('Other Lesson', $html);
    }

    private function createLesson(string $title, \DateTimeImmutable $schedule): Lesson
    {
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Lead',
            visualTheme: 'default',
            description: 'Desc',
            capacity: 10,
            duration: 60,
            ageRange: new AgeRange(3, 8),
            category: 'test',
        );
        return new Lesson($metadata, $schedule);
    }
}
