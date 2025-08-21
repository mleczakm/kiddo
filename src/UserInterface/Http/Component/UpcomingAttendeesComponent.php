<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class UpcomingAttendeesComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return Lesson[]
     */
    public function getLessons(): array
    {
        return $this->lessonRepository->findUpcomingWithBookings(new \DateTimeImmutable(), 5);
    }

    #[LiveAction]
    public function increaseCapacity(#[LiveArg] string $lessonId): void
    {
        $lesson = $this->lessonRepository->find($lessonId);
        if ($lesson) {
            $lesson->getMetadata()
                ->capacity++;
            $this->entityManager->flush();
        }
    }

    #[LiveAction]
    public function decreaseCapacity(#[LiveArg] string $lessonId): void
    {
        $lesson = $this->lessonRepository->find($lessonId);
        if ($lesson && $lesson->getMetadata()->capacity > count($lesson->getBookings())) {
            $lesson->getMetadata()
                ->capacity--;
            $this->entityManager->flush();
        }
    }
}
