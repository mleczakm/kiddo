<?php

declare(strict_types=1);

namespace App\Entity\DTO;

use Symfony\Component\Uid\Ulid;

class RescheduledLesson extends BookedLesson
{
    public function __construct(
        public readonly Ulid $lessonId,
        public readonly Ulid $rescheduledFrom,
        public readonly int $rescheduledBy,
        public readonly ?\DateTimeImmutable $rescheduledAt,
    ) {}
}
