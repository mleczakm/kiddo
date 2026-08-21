<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Service\BookingOccurrenceBackfill;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:booking-occurrences:backfill')]
final class BackfillBookingOccurrencesCommand extends Command
{
    public function __construct(
        private readonly BookingOccurrenceBackfill $backfill,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->backfill->run();
        $output->writeln(sprintf(
            'Bookings: %d; occurrences created: %d; projection mismatches: %d',
            $report['bookings'],
            $report['created'],
            $report['mismatches'],
        ));

        return $report['mismatches'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
