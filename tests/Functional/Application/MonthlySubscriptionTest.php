<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Application\Command\AddBooking;
use App\Application\Command\IssueSubscriptionCharges;
use App\Application\CommandHandler\IssueSubscriptionChargesHandler;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\WorkshopType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('functional')]
final class MonthlySubscriptionTest extends KernelTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testBuyingMonthlyCreatesSubscriptionBookingAndInvoice(): void
    {
        Clock::set(new MockClock('2026-10-05 09:00:00'));
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get(MessageBusInterface::class);

        [$user, $octoberLessons] = $this->persistWeeklySeriesWithMonthly($em);
        $anchor = $octoberLessons[0];

        $bus->dispatch(new AddBooking(
            userId: (int) $user->getId(),
            lessonId: (string) $anchor->getId(),
            ticketType: TicketType::MONTHLY->value,
            childId: null,
            paymentCode: 'MSUB',
        ));
        $em->clear();

        $subscriptions = $em->getRepository(Subscription::class)->findAll();
        static::assertCount(1, $subscriptions);
        static::assertSame('2026-10', $subscriptions[0]->getLastChargedPeriod());
        static::assertSame(16000, $subscriptions[0]->getMonthlyRateMinor());

        $bookings = $em->getRepository(Booking::class)->findBy(['user' => $user]);
        static::assertCount(1, $bookings);
        static::assertTrue($bookings[0]->isSubscription());
        // October has 3 future lessons from the anchor date.
        static::assertGreaterThanOrEqual(2, $bookings[0]->getLessons()->count());

        $payments = $em->getRepository(Payment::class)->findBy(['user' => $user]);
        static::assertCount(1, $payments);
        static::assertNotNull($payments[0]->getPaymentCode());
        static::assertStringContainsString('10.2026', (string) $payments[0]->getDescription());
    }

    public function testScheduledChargeBillsNextMonthOnce(): void
    {
        Clock::set(new MockClock('2026-10-05 09:00:00'));
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get(MessageBusInterface::class);
        /** @var IssueSubscriptionChargesHandler $issue */
        $issue = static::getContainer()->get(IssueSubscriptionChargesHandler::class);

        [$user, $octoberLessons] = $this->persistWeeklySeriesWithMonthly($em);

        $bus->dispatch(new AddBooking(
            userId: (int) $user->getId(),
            lessonId: (string) $octoberLessons[0]->getId(),
            ticketType: TicketType::MONTHLY->value,
            childId: null,
            paymentCode: 'MSB2',
        ));

        // Same month again -> nothing.
        $issue(new IssueSubscriptionCharges(new \DateTimeImmutable('2026-10-25')));
        static::assertCount(1, $em->getRepository(Payment::class)->findBy(['user' => $user]));

        // November -> one more invoice + booking.
        $issue(new IssueSubscriptionCharges(new \DateTimeImmutable('2026-11-02')));
        static::assertCount(2, $em->getRepository(Payment::class)->findBy(['user' => $user]));
        static::assertCount(2, $em->getRepository(Booking::class)->findBy(['user' => $user]));
    }

    /**
     * A weekly series with 4 October + 2 November lessons and a MONTHLY option.
     *
     * @return array{0: \App\Entity\User, 1: list<\App\Entity\Lesson>}
     */
    private function persistWeeklySeriesWithMonthly(EntityManagerInterface $em): array
    {
        $user = UserAssembler::new()->assemble();
        $metadata = LessonMetadataAssembler::new()->withTitle('Zajecia abonamentowe')->assemble();

        $lessons = [];
        foreach (['2026-10-07', '2026-10-14', '2026-10-21', '2026-10-28', '2026-11-04', '2026-11-11'] as $date) {
            $lessons[] = LessonAssembler::new()
                ->withMetadata($metadata)
                ->withSchedule(new \DateTimeImmutable($date . ' 16:30:00'))
                ->assemble();
        }

        $series = SeriesAssembler::new()
            ->withType(WorkshopType::WEEKLY)
            ->withTicketOptions([
                new TicketOption(
                    TicketType::MONTHLY,
                    Money::of(160, 'PLN'),
                    'Abonament',
                    TicketReschedulePolicy::NOT_ALLOWED,
                ),
            ])
            ->assemble();

        $em->persist($user);
        $em->persist($metadata);
        $em->persist($series);
        foreach ($lessons as $lesson) {
            // setSeries() wires both sides (adds the lesson to $series->lessons).
            $lesson->setSeries($series);
            $em->persist($lesson);
        }
        $em->flush();

        return [$user, array_slice($lessons, 0, 4)];
    }
}
