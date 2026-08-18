<?php

declare(strict_types=1);

namespace App\Application\Query\Lesson;

use App\Entity\Lesson;
use DateTimeImmutable;

interface TodayLessonsQuery
{
    /**
     * @return Lesson[]
     */
    public function forDate(DateTimeImmutable $date): array;
}
