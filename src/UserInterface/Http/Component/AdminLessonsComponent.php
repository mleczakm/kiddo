<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\Component\Uid\Ulid;
use App\Entity\Lesson;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminLessonsComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[LiveProp(writable: true, url: true)]
    public string $week;

    #[LiveProp(writable: true, url: true)]
    public bool $showCancelled = false;

    public function mount(): void
    {
        $this->week ??= Clock::get()->now()->format('Y-m-d');
    }

    /**
     * @return list<Lesson>
     */
    public function getLessons(): array
    {
        $start = new \DateTimeImmutable($this->week);
        $end = $start->modify('+7 days');

        /** @var list<Lesson> $lessons */
        $lessons = $this->lessonRepository->findUpcomingInRange($start, $end, $this->showCancelled);
        return $lessons;
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->week);
    }

    public function getWeekEnd(): \DateTimeImmutable
    {
        return $this->getWeekStart()
            ->modify('+7 days');
    }

    #[LiveAction]
    public function toggleCancelled(): void
    {
        $this->showCancelled = ! $this->showCancelled;
    }

    #[LiveAction]
    public function toggleStatus(#[LiveArg] string $lessonId): void
    {
        $id = Ulid::fromString($lessonId);
        $lesson = $this->lessonRepository->find($id);
        if (! $lesson) {
            return;
        }

        $lesson->status = $lesson->status === 'active' ? 'cancelled' : 'active';
        $this->entityManager->flush();
    }
}
