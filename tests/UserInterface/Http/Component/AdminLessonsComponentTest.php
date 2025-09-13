<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\UserInterface\Http\Component\AdminLessonsComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminLessonsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testEmptyStateShowsMessage(): void
    {
        $weekStart = new \DateTimeImmutable('2025-01-06');

        $component = $this->createLiveComponent(name: AdminLessonsComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        self::assertStringContainsString('Zajęcia', $html);
        self::assertStringContainsString('Brak zajęć w wybranym tygodniu', $html);
    }

    public function testHidesCancelledByDefaultAndShowsWhenToggled(): void
    {
        $weekStart = new \DateTimeImmutable('2025-01-13');

        $activeLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($weekStart->modify('+1 day'))->assemble())
            ->withTitle('Active Lesson')
            ->withStatus('active')
            ->assemble();

        $cancelledLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($weekStart->modify('+2 days'))->assemble())
            ->withTitle('Cancelled Lesson')
            ->withStatus('cancelled')
            ->assemble();

        $this->em->persist($activeLesson);
        $this->em->persist($cancelledLesson);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminLessonsComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);

        $html = (string) $component->render();
        self::assertStringContainsString('Active Lesson', $html);
        self::assertStringNotContainsString('Cancelled Lesson', $html);

        // Toggle to show cancelled
        $component->call('toggleCancelled');
        $html2 = (string) $component->render();
        self::assertStringContainsString('Cancelled Lesson', $html2);
    }

    public function testToggleStatusDisablesAndEnables(): void
    {
        $weekStart = new \DateTimeImmutable('2025-02-03');

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($weekStart->modify('+1 day'))->assemble())
            ->withTitle('Togglable Lesson')
            ->withStatus('active')
            ->assemble();

        $this->em->persist($lesson);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminLessonsComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);

        // Deactivate
        $component->call('toggleStatus', [
            'lessonId' => (string) $lesson->getId(),
        ]);
        $this->em->clear();

        $reloaded = $this->em->getRepository($lesson::class)->find($lesson->getId());
        self::assertNotNull($reloaded);
        self::assertSame('cancelled', $reloaded->status);

        // Reactivate
        $component->call('toggleStatus', [
            'lessonId' => (string) $lesson->getId(),
        ]);
        $this->em->clear();
        $reloaded2 = $this->em->getRepository($lesson::class)->find($lesson->getId());
        self::assertNotNull($reloaded2);
        self::assertSame('active', $reloaded2->status);
    }

    public function testWeekFilteringOnlyShowsSelectedWeek(): void
    {
        $weekStart = new \DateTimeImmutable('2025-02-10');
        $prevWeek = $weekStart->modify('-7 days');

        $inWeek = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($weekStart->modify('+1 day'))->assemble())
            ->withTitle('In Week Lesson')
            ->withStatus('active')
            ->assemble();
        $outOfWeek = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($prevWeek->modify('+1 day'))->assemble())
            ->withTitle('Out Lesson')
            ->withStatus('active')
            ->assemble();

        $this->em->persist($inWeek);
        $this->em->persist($outOfWeek);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminLessonsComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        self::assertStringContainsString('In Week Lesson', $html);
        self::assertStringNotContainsString('Out Lesson', $html);
    }
}
