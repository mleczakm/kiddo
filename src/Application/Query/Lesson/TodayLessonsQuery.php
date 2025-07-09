<?php

declare(strict_types=1);

namespace App\Application\Query\Lesson;

use DateTimeImmutable;
use App\Entity\Lesson;

interface TodayLessonsQuery
{
    /**
     * @return Lesson[]
     */
    public function forDate(DateTimeImmutable $date): array;
}
