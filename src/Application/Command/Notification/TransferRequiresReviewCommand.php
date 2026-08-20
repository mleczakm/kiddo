<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use App\Entity\Transfer;

final readonly class TransferRequiresReviewCommand
{
    public function __construct(
        public Transfer $transfer,
    ) {}
}
