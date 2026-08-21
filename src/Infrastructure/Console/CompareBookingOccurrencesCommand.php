<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Repository\BookingRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:booking-occurrences:compare')]
final class CompareBookingOccurrencesCommand extends Command
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mismatches = 0;
        foreach ($this->bookings->findAll() as $booking) {
            $normalized = [];
            foreach ($booking->getOccurrences() as $occurrence) {
                $normalized[(string) $occurrence->getLesson()->getId()] = $occurrence->getStatus();
            }
            $legacy = [];
            foreach ($booking->getLessons() as $lesson) {
                $legacy[(string) $lesson->getId()] = match (true) {
                    $booking->getLessonsMap()->isRescheduledLesson($lesson->getId()) => 'rescheduled',
                    $booking->getLessonsMap()->isCancelledLesson($lesson->getId()) => 'cancelled',
                    $booking->getLessonsMap()->past->hasKey($lesson->getId()) => 'attended',
                    $booking->isConfirmed() => 'confirmed',
                    default => 'reserved',
                };
            }
            ksort($normalized);
            ksort($legacy);
            if ($normalized !== $legacy) {
                ++$mismatches;
                $output->writeln(sprintf('<error>%s differs</error>', $booking->getId()));
            }
        }
        $output->writeln(sprintf('Projection mismatches: %d', $mismatches));
        return $mismatches === 0 ? self::SUCCESS : self::FAILURE;
    }
}
