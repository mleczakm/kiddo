<?php

declare(strict_types=1);

namespace App\UserInterface\Console;

use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketType;
use App\Entity\WorkshopType;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-workshops', description: 'Seed the database with workshop data',)]
class SeedWorkshopsCommand extends Command
{
    private const int WEEKS_AHEAD = 12; // Number of weeks to generate workshops for

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding workshop data');

        $this->seedWorkshops($io);

        $io->success('Workshop data seeded successfully!');
        return Command::SUCCESS;
    }

    private function seedWorkshops(SymfonyStyle $io): void
    {
        $today = new \DateTimeImmutable();
        $io->section('Creating workshops');

        // Create users
        $users = $this->createUsers($io);

        // Create one-time events first
        //        $this->createOneTimeEvents($io);

        // Then create weekly series
        $lessons = $this->createWeeklySeries($io);

        // Create bookings, payments, and transfers
        $this->createSampleBookingsPaymentsTransfers($lessons, $users, $io);
    }

    /**
     * @return list<\App\Entity\User>
     */
    private function createUsers(SymfonyStyle $io): array
    {
        require_once __DIR__ . '/../../../tests/Assembler/UserAssembler.php';
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $email = sprintf('parent%d+%s@example.com', $i, bin2hex(random_bytes(3)));
            $existing = $this->entityManager->getRepository(\App\Entity\User::class)->findOneBy([
                'email' => $email,
            ]);
            if ($existing) {
                $users[] = $existing;
                continue;
            }
            $user = \App\Tests\Assembler\UserAssembler::new()
                ->withEmail($email)
                ->withName(sprintf('Parent %d', $i))
                ->assemble();
            $this->entityManager->persist($user);
            $users[] = $user;
        }
        $this->entityManager->flush();
        $io->success('Created sample users');
        return $users;
    }

    /**
     * @return list<Lesson>
     */
    private function createWeeklySeries(SymfonyStyle $io): array
    {
        $io->section('Creating weekly workshop series');
        $allLessons = [];

        // 1. Klub Malucha - Poniedziałek 9:00-11:30
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Klub Malucha - Poniedziałek',
            lead: 'Zajęcia dla najmłodszych dzieci',
            visualTheme: 'rgb(255, 223, 186)',
            description: 'Zajęcia ogólnorozwojowe dla najmłodszych dzieci, wspierające rozwój społeczny, emocjonalny i motoryczny.',
            capacity: 8,
            startTime: '09:00',
            dayOfWeek: 1, // Monday
            duration: 150, // 2.5 hours
            ageRange: new AgeRange(1, 3),
            category: 'Rozwój ogólny',
            price: Money::of(80, 'PLN'),
            io: $io,
        ));

        // 2. Klub Malucha - Wtorek 9:00-11:30
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Klub Malucha - Wtorek',
            lead: 'Zajęcia dla najmłodszych dzieci',
            visualTheme: 'rgb(255, 223, 186)',
            description: 'Zajęcia ogólnorozwojowe dla najmłodszych dzieci, wspierające rozwój społeczny, emocjonalny i motoryczny.',
            capacity: 8,
            startTime: '09:00',
            dayOfWeek: 2, // Tuesday
            duration: 150, // 2.5 hours
            ageRange: new AgeRange(1, 3),
            category: 'Rozwój ogólny',
            price: Money::of(80, 'PLN'),
            io: $io,
        ));

        // 3. Budujemy Relacje - Trening Umiejętności Społecznych - Wtorek 17:00
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Budujemy Relacje - Trening Umiejętności Społecznych',
            lead: 'Warsztat dla dzieci 4-7 lat',
            visualTheme: 'rgb(186, 255, 201)',
            description: 'Zajęcia rozwijające umiejętności społeczne, komunikacyjne i emocjonalne u dzieci.',
            capacity: 8,
            startTime: '17:00',
            dayOfWeek: 2, // Tuesday
            duration: 60,
            ageRange: new AgeRange(4, 7),
            category: 'Rozwój społeczny',
            price: Money::of(60, 'PLN'),
            io: $io,
        ));

        // 4. Bałaganki - Środa 10:00
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Bałaganki',
            lead: 'Zajęcia sensoryczno-plastyczne',
            visualTheme: 'rgb(255, 200, 200)',
            description: 'Zajęcia plastyczne z elementami sensoplastyki, rozwijające kreatywność i integrację sensoryczną.',
            capacity: 8,
            startTime: '10:00',
            dayOfWeek: 3, // Wednesday
            duration: 60,
            ageRange: new AgeRange(2, 5),
            category: 'Sztuka i rozwój sensoryczny',
            price: Money::of(50, 'PLN'),
            io: $io,
        ));

        // 5. Senso Bobasy - Środa 11:45
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Senso Bobasy',
            lead: 'Zajęcia sensoryczne dla niemowląt',
            visualTheme: 'rgb(200, 230, 255)',
            description: 'Zajęcia sensoryczne wspierające rozwój niemowląt poprzez zabawy stymulujące zmysły.',
            capacity: 8,
            startTime: '11:45',
            dayOfWeek: 3, // Wednesday
            duration: 45,
            ageRange: new AgeRange(0, 2),
            category: 'Rozwój sensoryczny',
            price: Money::of(60, 'PLN'),
            io: $io,
        ));

        // 6. Rodzinne muzykowanie z Pomelody - Czwartek 10:00
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Rodzinne muzykowanie z Pomelody',
            lead: 'Zajęcia umuzykalniające dla rodzin z dziećmi',
            visualTheme: 'rgb(238, 203, 233)',
            description: 'Zajęcia umuzykalniające metodą Pomelody, łączące zabawę z nauką rytmu i melodii.',
            capacity: 10,
            startTime: '10:00',
            dayOfWeek: 4, // Thursday
            duration: 45,
            ageRange: new AgeRange(0, 5),
            category: 'Muzyka i ruch',
            price: Money::of(59, 'PLN'),
            io: $io,
        ));

        // 7. Ćwiczymy mózg przez ruch - Czwartek 16:30
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Ćwiczymy mózg przez ruch - Czwartek',
            lead: 'Zajęcia ruchowe wspierające rozwój mózgu',
            visualTheme: 'rgb(200, 255, 200)',
            description: 'Zajęcia ruchowe oparte na metodzie integracji sensorycznej, wspierające rozwój układu nerwowego.',
            capacity: 8,
            startTime: '16:30',
            dayOfWeek: 4, // Thursday
            duration: 60,
            ageRange: new AgeRange(3, 6),
            category: 'Rozwój ruchowy',
            price: Money::of(55, 'PLN'),
            io: $io,
        ));

        // 8. Ćwiczymy mózg przez ruch - Piątek 10:00
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Ćwiczymy mózg przez ruch - Piątek',
            lead: 'Zajęcia ruchowe wspierające rozwój mózgu',
            visualTheme: 'rgb(200, 255, 200)',
            description: 'Zajęcia ruchowe oparte na metodzie integracji sensorycznej, wspierające rozwój układu nerwowego.',
            capacity: 8,
            startTime: '10:00',
            dayOfWeek: 5, // Friday
            duration: 60,
            ageRange: new AgeRange(3, 6),
            category: 'Rozwój ruchowy',
            price: Money::of(55, 'PLN'),
            io: $io,
        ));

        // 9. Ćwiczymy mózg przez ruch - Piątek 11:45
        $allLessons = array_merge($allLessons, $this->createWorkshopSeries(
            title: 'Ćwiczymy mózg przez ruch - Piątek późne przedpołudnie',
            lead: 'Zajęcia ruchowe wspierające rozwój mózgu',
            visualTheme: 'rgb(200, 255, 200)',
            description: 'Zajęcia ruchowe oparte na metodzie integracji sensorycznej, wspierające rozwój układu nerwowego.',
            capacity: 8,
            startTime: '11:45',
            dayOfWeek: 5, // Friday
            duration: 60,
            ageRange: new AgeRange(3, 6),
            category: 'Rozwój ruchowy',
            price: Money::of(55, 'PLN'),
            io: $io,
        ));

        return $allLessons;
    }

    /**
     * @return list<Lesson>
     */
    private function createWorkshopSeries(
        string $title,
        string $lead,
        string $visualTheme,
        string $description,
        int $capacity,
        string $startTime,
        int $dayOfWeek,
        int $duration,
        AgeRange $ageRange,
        string $category,
        Money $price,
        SymfonyStyle $io,
        bool $isBiWeekly = false
    ): array {
        $today = new \DateTimeImmutable();
        $lessons = new ArrayCollection();

        // Create a series of lessons for the next WEEKS_AHEAD weeks
        $lessonEntities = [];
        for ($i = 0; $i < self::WEEKS_AHEAD; $i++) {
            // Skip if bi-weekly and this is an odd week
            if ($isBiWeekly && $i % 2 !== 0) {
                continue;
            }

            // Find the next occurrence of the specified day of week
            $daysUntilNext = ($dayOfWeek - (int) $today->format('N') + 7) % 7;
            $daysUntilNext += $i * 7; // Add weeks

            $date = $today->modify("+{$daysUntilNext} days");
            [$hours, $minutes] = explode(':', $startTime);
            $dateTime = $date->setTime((int) $hours, (int) $minutes);

            // Skip if the date is in the past
            if ($dateTime < $today) {
                continue;
            }

            $metadata = new LessonMetadata(
                title: $title,
                lead: $lead,
                visualTheme: $visualTheme,
                description: $description,
                capacity: $capacity,
                schedule: $dateTime,
                duration: $duration,
                ageRange: $ageRange,
                category: $category,
            );

            $lesson = new Lesson($metadata);
            $lessonEntities[] = $lesson;
            $this->entityManager->persist($lesson);
        }

        $lessons = new ArrayCollection($lessonEntities);

        if (count($lessonEntities) === 0) {
            $io->warning(sprintf('No valid dates found for workshop: %s', $title));
            return [];
        }

        // Create a series with ticket options
        $carnetPrice = match (true) {
            // Klub Malucha - 320 zł for 4 classes
            str_contains($title, 'Klub Malucha') => Money::of(320, 'PLN'),
            // TUS (Budujemy Relacje) - 240 zł for 4 classes
            str_contains($title, 'Budujemy Relacje') => Money::of(240, 'PLN'),
            // Bałaganki - 180 zł for 4 classes
            str_contains($title, 'Bałaganki') => Money::of(180, 'PLN'),
            // Senso Bobasy - 200 zł for 4 classes
            str_contains($title, 'Senso Bobasy') => Money::of(200, 'PLN'),
            // Ćwiczymy mózg - 220 zł for 4 classes
            str_contains($title, 'Ćwiczymy mózg') => Money::of(220, 'PLN'),
            // Default: 10% discount for 4 classes
            default => $price->multipliedBy(4)
                ->multipliedBy(0.9)
        };

        $ticketOptions = [
            new TicketOption(TicketType::ONE_TIME, $price),
            new TicketOption(TicketType::CARNET_4, $carnetPrice),
        ];

        $series = new Series(
            lessons: $lessons,
            type: $isBiWeekly ? WorkshopType::WEEKLY : WorkshopType::WEEKLY,
            ticketOptions: $ticketOptions
        );

        // Set the series for each lesson
        foreach ($lessons as $lesson) {
            $lesson->setSeries($series);
        }

        $this->entityManager->persist($series);
        $this->entityManager->flush();

        $firstDate = 'N/A';
        $lastDate = 'N/A';

        /** @var Lesson[] $lessonsArray */
        $lessonsArray = $lessons->toArray();

        if (count($lessonsArray) > 0) {
            $firstDate = $lessonsArray[0]->getMetadata()->schedule->format('Y-m-d');
            $lastDate = $lessonsArray[count($lessonsArray) - 1]->getMetadata()->schedule->format('Y-m-d');
        }

        $io->success(sprintf(
            'Created %s: %d lessons from %s to %s',
            $title,
            $lessons->count(),
            $firstDate,
            $lastDate
        ));

        return $lessonEntities;
    }

    /**
     * @param list<Lesson> $lessons
     * @param list<\App\Entity\User> $users
     */
    private function createSampleBookingsPaymentsTransfers(array $lessons, array $users, SymfonyStyle $io): void
    {
        require_once __DIR__ . '/../../../tests/Assembler/PaymentAssembler.php';
        require_once __DIR__ . '/../../../tests/Assembler/TransferAssembler.php';
        require_once __DIR__ . '/../../../tests/Assembler/BookingAssembler.php';
        $io->section('Creating sample bookings, payments, and transfers');
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('DELETE FROM booking');
        $conn->executeStatement('DELETE FROM payment');
        $conn->executeStatement('DELETE FROM transfer');

        // Create a payment and transfer for each user
        $payments = [];
        $transfers = [];
        foreach ($users as $user) {
            $payment = \App\Tests\Assembler\PaymentAssembler::new()
                ->withAmount(\Brick\Money\Money::of(80, 'PLN'))
                ->withStatus('completed')
                ->withCreatedAt(new \DateTimeImmutable('-1 day'))
                ->withUser($user)
                ->assemble();
            $this->entityManager->persist($payment);
            $payments[] = $payment;

            $transfer = \App\Tests\Assembler\TransferAssembler::new()
                ->withAmount('80.00')
                ->withTransferredAt(new \DateTimeImmutable('-1 day'))
                ->assemble();
            $this->entityManager->persist($transfer);
            $transfers[] = $transfer;
        }

        // Create bookings for almost every lesson, for all users
        $bookedLessonIds = [];
        foreach ($lessons as $i => $lesson) {
            foreach ($users as $j => $user) {
                // Randomly skip some lessons to avoid 100% booking
                if (random_int(0, 9) < 1) {
                    continue;
                }
                $booking = \App\Tests\Assembler\BookingAssembler::new()
                    ->withUser($user)
                    ->withPayment($payments[$j % count($payments)])
                    ->withLessons($lesson)
                    ->withStatus('confirmed')
                    ->assemble();
                $this->entityManager->persist($booking);
                $bookedLessonIds[] = $lesson->getId();
            }
        }
        $this->entityManager->flush();

        $io->success(
            'Created sample bookings for almost every lesson, and removed old lessons/series without bookings.'
        );
    }
}
