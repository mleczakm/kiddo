<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Entity\DTO\LessonMap;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:booking:update-lesson-maps',
    description: 'Iterates through all bookings and updates their lesson maps.',
)]
class UpdateBookingLessonMapsCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $bookings = $this->bookingRepository->findAll();
        $bookingCount = count($bookings);

        if ($bookingCount === 0) {
            $io->success('No bookings found to update.');
            return Command::SUCCESS;
        }

        $io->progressStart($bookingCount);

        foreach ($bookings as $booking) {
            $newLessonMap = LessonMap::createFromBooking($booking);
            $booking->setLessonsMap($newLessonMap);
            $io->progressAdvance();
        }

        $this->entityManager->flush();

        $io->progressFinish();
        $io->success(sprintf('Successfully updated lesson maps for %d bookings.', $bookingCount));

        return Command::SUCCESS;
    }
}
