<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\Entity\Series;
use App\Entity\User;
use App\Repository\LessonRepository;
use App\Security\Voter\LessonVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Single admin view over the weekly schedule: a Series (recurring template)
 * grouped with its own Lesson occurrences that fall in the selected week.
 * Lessons without a Series (edge case — series is optional on Lesson) are
 * rendered as their own ungrouped row. This replaces what used to be two
 * separate tabs ("Warsztaty" showing Series, "Zajęcia" showing Lessons),
 * which showed overlapping/inconsistent views of the same underlying data.
 */
#[AsLiveComponent]
final class AdminScheduleComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[LiveProp(writable: true, url: true)]
    public string $week;

    #[LiveProp(writable: true, url: true)]
    public bool $showCancelled = false;

    #[LiveProp(writable: true)]
    public ?Ulid $editSeriesId = null;

    #[LiveProp(writable: true)]
    public ?Ulid $previewSeriesId = null;

    #[LiveProp(writable: true)]
    public bool $endingSeries = false;

    #[LiveProp(writable: true)]
    public bool $showAddModal = false;

    public function mount(): void
    {
        $this->week ??= Clock::get()->now()->format('Y-m-d');
    }

    /**
     * @return list<Lesson>
     */
    private function getLessons(): array
    {
        $start = new \DateTimeImmutable($this->week);
        $end = $start->modify('+7 days');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return [];
            }

            /** @var list<Lesson> $lessons */
            $lessons = $this->lessonRepository->findUpcomingInRangeForInstructor(
                $start,
                $end,
                $this->showCancelled,
                $user,
            );
        } else {
            /** @var list<Lesson> $lessons */
            $lessons = $this->lessonRepository->findUpcomingInRange($start, $end, $this->showCancelled);
        }

        if (!$this->showCancelled) {
            // A cancelled Series doesn't cancel its individual Lessons (each
            // keeps its own status) — filter those out here so a cancelled
            // series' occurrences stay hidden by default, same as before.
            $lessons = array_values(array_filter(
                $lessons,
                static fn(Lesson $l) => $l->getSeries() === null || $l->getSeries()?->status === 'active',
            ));
        }

        return $lessons;
    }

    /**
     * Lessons in the selected week, grouped by their Series (each group keeps
     * the full Series entity so series-wide info like getFirstLesson()/
     * getLastLesson() still spans the whole series, not just this week).
     * Lessons without a Series get their own group with series: null.
     *
     * @return list<array{series: ?Series, lessons: list<Lesson>}>
     */
    public function getGroups(): array
    {
        $groups = [];
        $order = [];
        foreach ($this->getLessons() as $lesson) {
            $series = $lesson->getSeries();
            $key = $series !== null ? (string) $series->getId() : 'lesson-' . (string) $lesson->getId();
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'series' => $series,
                    'lessons' => [],
                ];
                $order[] = $key;
            }
            $groups[$key]['lessons'][] = $lesson;
        }

        return array_map(static fn(string $key) => $groups[$key], $order);
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->week);
    }

    public function getWeekEnd(): \DateTimeImmutable
    {
        return $this->getWeekStart()->modify('+7 days');
    }

    /**
     * @return list<string>
     */
    public function getTicketTypes(Series $series): array
    {
        $types = [];
        foreach ($series->ticketOptions as $opt) {
            $types[] = $opt->type->value;
        }
        return $types;
    }

    #[LiveAction]
    public function startEdit(#[LiveArg] string $seriesId): void
    {
        $this->previewSeriesId = null;
        $this->endingSeries = false;
        $this->editSeriesId = Ulid::fromString($seriesId);
    }

    #[LiveAction]
    public function openPreview(#[LiveArg] string $seriesId): void
    {
        $this->previewSeriesId = Ulid::fromString($seriesId);
    }

    #[LiveAction]
    public function closePreview(): void
    {
        $this->previewSeriesId = null;
    }

    #[LiveAction]
    public function endSeries(#[LiveArg] string $seriesId): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_SCHEDULE');
        $this->previewSeriesId = null;
        $this->endingSeries = true;
        $this->editSeriesId = Ulid::fromString($seriesId);
    }

    #[LiveAction]
    public function openAddModal(): void
    {
        $this->showAddModal = true;
    }

    #[LiveListener('workshopEditorClosed')]
    #[LiveListener('workshopEditorSaved')]
    public function onWorkshopEditorClosed(): void
    {
        $this->editSeriesId = null;
        $this->endingSeries = false;
        $this->showAddModal = false;
    }

    #[LiveAction]
    public function toggleCancelled(): void
    {
        $this->showCancelled = !$this->showCancelled;
    }

    #[LiveAction]
    public function toggleLessonStatus(#[LiveArg] string $lessonId): void
    {
        $id = Ulid::fromString($lessonId);
        $lesson = $this->em->find(Lesson::class, $id);
        if (!$lesson instanceof Lesson) {
            return;
        }

        if (!$this->isGranted(LessonVoter::MANAGE, $lesson)) {
            return;
        }

        $lesson->status = $lesson->status === 'active' ? 'cancelled' : 'active';
        $this->em->flush();
    }
}
