<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Command;

use PHPUnit\Framework\Attributes\Group;
use App\Application\Command\SeedDemoDataCommand;
use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('functional')]
final class SeedDemoDataCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(SeedDemoDataCommand::class);
        self::assertInstanceOf(SeedDemoDataCommand::class, $command);
        $this->commandTester = new CommandTester($command);
        $this->commandTester->execute([
            '--purge' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->commandTester->execute([
            '--purge' => true,
        ]);
        parent::tearDown();
    }

    public function testItCreatesACompleteAndRepeatableDemoDataset(): void
    {
        self::assertSame(0, $this->commandTester->execute([
            '--replace' => true,
        ]));
        self::assertStringContainsString('9 bookings', $this->commandTester->getDisplay());

        $users = self::getContainer()->get(UserRepository::class);
        $lessons = self::getContainer()->get(LessonRepository::class);
        $bookings = self::getContainer()->get(BookingRepository::class);
        /** @var list<object> $demoUsers */
        $demoUsers = $users->createQueryBuilder('u')
            ->where('u.email LIKE :domain')
            ->setParameter('domain', '%@demo.kiddo.local')
            ->getQuery()
            ->getResult();
        self::assertCount(6, $demoUsers);
        /** @var list<object> $demoLessons */
        $demoLessons = $lessons->createQueryBuilder('l')
            ->join('l.metadata', 'm')
            ->where('m.title LIKE :prefix')
            ->setParameter('prefix', '[DEMO] %')
            ->getQuery()
            ->getResult();
        self::assertCount(10, $demoLessons);

        /** @var list<Booking> $demoBookings */
        $demoBookings = $bookings->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->where('u.email LIKE :domain')
            ->setParameter('domain', '%@demo.kiddo.local')
            ->getQuery()
            ->getResult();
        self::assertCount(9, $demoBookings);
        self::assertContains(
            Booking::STATUS_PENDING,
            array_map(static fn(Booking $booking): string => $booking->getStatus(), $demoBookings)
        );
        self::assertContains(
            Booking::STATUS_WAITING_APPROVAL,
            array_map(static fn(Booking $booking): string => $booking->getStatus(), $demoBookings)
        );
        self::assertTrue(array_any($demoBookings, static fn(Booking $booking): bool => $booking->hasBeenRescheduled()));

        self::assertSame(0, $this->commandTester->execute([]));
        self::assertStringContainsString('already exists', $this->commandTester->getDisplay());
        /** @var list<Booking> $unchangedBookings */
        $unchangedBookings = $bookings->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->where('u.email LIKE :domain')
            ->setParameter('domain', '%@demo.kiddo.local')
            ->getQuery()
            ->getResult();
        self::assertCount(9, $unchangedBookings);
    }
}
