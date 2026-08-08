<?php

declare(strict_types=1);

namespace App\Entity\DTO;

use Symfony\Component\Uid\Ulid;

class CancelledLesson extends BookedLesson
{
    public function __construct(
        public readonly Ulid $lessonId,
        public readonly ?int $cancelledBy,
        public readonly ?\DateTimeImmutable $cancelledAt,
        public readonly ?string $reason = null,
    ) {}
}
