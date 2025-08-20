<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use App\Entity\Transfer;

final readonly class TransferNotMatchedCommand
{
    public function __construct(
        public Transfer $transfer,
    ) {}
}
