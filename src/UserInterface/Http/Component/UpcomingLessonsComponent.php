<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\Repository\LessonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class UpcomingLessonsComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly LessonRepository $lessonRepository,
    ) {}

    /**
     * @return Lesson[]
     */
    public function getLessons(): array
    {
        return $this->lessonRepository->findUpcoming(new \DateTimeImmutable(), 10);
    }
}
