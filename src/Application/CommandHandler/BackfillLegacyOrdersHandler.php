<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\BackfillLegacyOrders;
use App\Application\Service\LegacyOrderBackfill;
use Psr\Log\LoggerInterface;

final readonly class BackfillLegacyOrdersHandler
{
    public function __construct(
        private LegacyOrderBackfill $backfill,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws \ArithmeticError
     * @throws \Brick\Math\Exception\MathException
     * @throws \DivisionByZeroError
     */
    public function __invoke(BackfillLegacyOrders $command): void
    {
        $this->logger->info('Legacy order backfill batch completed', $this->backfill->run($command->limit));
    }
}
