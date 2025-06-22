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

    private const array ONE_TIME_EVENTS = [
        [
            'title' => 'Warsztaty plastyczne',
            'lead' => 'Tworzenie prac plastycznych różnymi technikami',
            'visualTheme' => 'rgb(255, 200, 200)',
            'description' => 'Zajęcia plastyczne rozwijające kreatywność i zdolności manualne.',
            'capacity' => 10,
            'date' => '2025-07-10',
            'startTime' => '16:00',
            'duration' => 90,
            'ageRange' => [4, 8],
            'category' => 'Sztuka',
            'price' => 60.00,
        ],
        [
            'title' => 'Teatrzyk dla dzieci',
            'lead' => 'Zabawa w teatr dla najmłodszych',
            'visualTheme' => 'rgb(200, 200, 255)',
            'description' => 'Zajęcia teatralne rozwijające wyobraźnię i umiejętności aktorskie.',
            'capacity' => 12,
            'date' => '2025-07-17',
            'startTime' => '17:00',
            'duration' => 60,
            'ageRange' => [3, 6],
            'category' => 'Teatr',
            'price' => 50.00,
        ],
    ];

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

        // Create one-time events first
        $this->createOneTimeEvents($io);

        // Then create weekly series
        $this->createWeeklySeries($io);
    }

    private function createOneTimeEvents(SymfonyStyle $io): void
    {
        $io->section('Creating one-time events');

        foreach (self::ONE_TIME_EVENTS as $event) {
            $date = new \DateTimeImmutable($event['date'] . ' ' . $event['startTime']);

            // Skip if the event is in the past
            if ($date < new \DateTimeImmutable()) {
                $io->note(sprintf('Skipping past one-time event: %s', $event['title']));
                continue;
            }

            $metadata = new LessonMetadata(
                title: $event['title'],
                lead: $event['lead'],
                visualTheme: $event['visualTheme'],
                description: $event['description'],
                capacity: $event['capacity'],
                schedule: $date,
                duration: $event['duration'],
                ageRange: new AgeRange($event['ageRange'][0], $event['ageRange'][1]),
                category: $event['category'],
            );

            $lesson = new Lesson($metadata);

            // Create a series for this one-time event
            $ticketOptions = [new TicketOption(TicketType::ONE_TIME, Money::of($event['price'], 'PLN'))];

            $series = new Series(
                lessons: new ArrayCollection([$lesson]),
                type: WorkshopType::ONE_TIME,
                ticketOptions: $ticketOptions
            );

            $lesson->setSeries($series);

            $this->entityManager->persist($lesson);
            $this->entityManager->persist($series);

            $io->success(sprintf('Created one-time event: %s on %s', $event['title'], $date->format('Y-m-d H:i')));
        }

        $this->entityManager->flush();
    }

    private function createWeeklySeries(SymfonyStyle $io): void
    {
        $io->section('Creating weekly workshop series');

        // 1. Rodzinne Muzykowanie z Pomelody (Weekly on Thursdays at 16:30)
        $this->createWorkshopSeries(
            title: 'Rodzinne Muzykowanie z Pomelody',
            lead: 'Zajęcia umuzykalniające dla rodzin z dziećmi 0-6 lat',
            visualTheme: 'rgb(238, 203, 233)',
            description: 'Warsztaty prowadzone w języku polskim i angielskim, wspierające rozwój muzyczny dzieci.',
            capacity: 12,
            startTime: '16:30',
            dayOfWeek: 4, // Thursday
            duration: 60,
            ageRange: new AgeRange(0, 6),
            category: 'Muzyka, Taniec, Śpiew',
            price: Money::of(40, 'PLN'),
            io: $io,
        );

        // 2. Klub Malucha (Weekly on Wednesdays at 10:00)
        $this->createWorkshopSeries(
            title: 'Klub Malucha',
            lead: 'Zajęcia dla najmłodszych dzieci',
            visualTheme: 'rgb(255, 223, 186)',
            description: 'Warsztaty wspierające rozwój społeczny i emocjonalny dzieci.',
            capacity: 10,
            startTime: '10:00',
            dayOfWeek: 3, // Wednesday
            duration: 90,
            ageRange: new AgeRange(1, 3),
            category: 'Rozwój społeczny',
            price: Money::of(45, 'PLN'),
            io: $io,
        );

        // 3. Budujemy Relacje (Weekly on Tuesdays at 16:00)
        $this->createWorkshopSeries(
            title: 'Budujemy Relacje',
            lead: 'Warsztat dla dzieci 3-6 lat',
            visualTheme: 'rgb(186, 255, 201)',
            description: 'Trening umiejętności społecznych, rozwój współpracy i pokonywanie nieśmiałości.',
            capacity: 12,
            startTime: '16:00',
            dayOfWeek: 2, // Tuesday
            duration: 60,
            ageRange: new AgeRange(3, 6),
            category: 'Umiejętności społeczne',
            price: Money::of(45, 'PLN'),
            io: $io,
        );

        // 4. Ćwiczymy Mózg przez Ruch (Weekly on Mondays at 16:00)
        $this->createWorkshopSeries(
            title: 'Ćwiczymy Mózg przez Ruch',
            lead: 'Warsztat bazujący na terapii zaburzeń integracji sensorycznej',
            visualTheme: 'rgb(186, 238, 255)',
            description: 'Zajęcia ruchowe wspierające rozwój układu nerwowego i sensorycznego.',
            capacity: 10,
            startTime: '16:00',
            dayOfWeek: 1, // Monday
            duration: 60,
            ageRange: new AgeRange(4, 7),
            category: 'Rozwój ruchowy',
            price: Money::of(50, 'PLN'),
            io: $io,
        );

        // 5. Fabryka Czekolady (Bi-weekly on Fridays at 16:30)
        $this->createWorkshopSeries(
            title: 'Fabryka Czekolady',
            lead: 'Warsztat dla dzieci 2-4 lata',
            visualTheme: 'rgb(255, 238, 186)',
            description: 'Dekorowanie czekolady, poznawanie historii i próbowanie różnych rodzajów czekolady.',
            capacity: 8,
            startTime: '16:30',
            dayOfWeek: 5, // Friday
            duration: 90,
            ageRange: new AgeRange(2, 4),
            category: 'Kreatywność',
            price: Money::of(55, 'PLN'),
            isBiWeekly: true,
            io: $io,
        );
    }

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
    ): void {
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
            return;
        }

        // Create a series with ticket options
        $ticketOptions = [
            new TicketOption(TicketType::ONE_TIME, $price),
            new TicketOption(TicketType::CARNET_4, $price->multipliedBy(4)->multipliedBy(
                0.9
            )), // 10% discount for 4 classes
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
    }
}
