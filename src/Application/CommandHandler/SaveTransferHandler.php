<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SaveTransfer;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SaveTransferHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function __invoke(SaveTransfer $command): void
    {
        $this->entityManager->persist($command->transfer);
    }
}
