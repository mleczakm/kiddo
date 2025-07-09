<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Query;

use App\Application\Query\Lesson\TodayLessonsQuery;
use App\Entity\Lesson;
use App\Repository\LessonRepository;
use DateTimeImmutable;

readonly class DoctrineTodayLessonsQuery implements TodayLessonsQuery
{
    public function __construct(
        private LessonRepository $lessonRepository
    ) {}

    /**
     * @return Lesson[]
     */
    public function forDate(DateTimeImmutable $date): array
    {
        return $this->lessonRepository->findByDate($date);
    }
}
