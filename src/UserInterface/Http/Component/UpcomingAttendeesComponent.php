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
final class UpcomingAttendeesComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $week;

    #[LiveProp(writable: true, url: true)]
    public bool $showCancelled = false;

    public function __construct(
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->week = Clock::get()->now()->format('Y-m-d');
    }

    /**
     * @return Lesson[]
     */
    public function getLessons(): array
    {
        $startDate = new \DateTimeImmutable($this->week);
        $endDate = $startDate->modify('+7 days');

        $lessons = $this->lessonRepository->findUpcomingWithBookingsInRange($startDate, $endDate, $this->showCancelled);

        return $lessons;
    }

    public function getWeekEnd(): \DateTimeImmutable
    {
        return $this->getWeekStart()
            ->modify('+7 days');
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->week);
    }

    #[LiveAction]
    public function increaseCapacity(#[LiveArg] string $lessonId): void
    {
        // Convert incoming ULID string to Ulid object to ensure proper DBAL binding
        $id = Ulid::fromString($lessonId);
        $lesson = $this->lessonRepository->find($id);
        if ($lesson) {
            $lesson->getMetadata()
                ->capacity++;
            $this->entityManager->flush();
        }
    }

    #[LiveAction]
    public function decreaseCapacity(#[LiveArg] string $lessonId): void
    {
        $id = Ulid::fromString($lessonId);
        $lesson = $this->lessonRepository->find($id);
        if ($lesson && $lesson->getAvailableSpots() > 0) {
            $lesson->getMetadata()
                ->capacity--;
            $this->entityManager->flush();
        }
    }

    #[LiveAction]
    public function toggleCancelled(): void
    {
        $this->showCancelled = ! $this->showCancelled;
    }
}
