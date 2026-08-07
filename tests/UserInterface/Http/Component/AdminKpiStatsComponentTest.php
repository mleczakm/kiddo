<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Payment;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminKpiStatsComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminKpiStatsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testKpiStatsReflectRealDataForTheCurrentMonthOnly(): void
    {
        Clock::set(new MockClock('2025-03-15 12:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $customer = UserAssembler::new()->assemble();
        $em->persist($customer);

        $em->persist(BookingAssembler::new()
            ->withUser($customer)
            ->withCreatedAt(new \DateTimeImmutable('2025-03-10'))
            ->withStatus('active')
            ->assemble());
        $em->persist(BookingAssembler::new()
            ->withUser($customer)
            ->withCreatedAt(new \DateTimeImmutable('2025-02-10'))
            ->withStatus('active')
            ->assemble());
        $em->persist(BookingAssembler::new()
            ->withUser($customer)
            ->withCreatedAt(new \DateTimeImmutable('2025-03-12'))
            ->withStatus('cancelled')
            ->assemble());

        $em->persist(PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(100, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->withCreatedAt(new \DateTimeImmutable('2025-03-05'))
            ->assemble());
        $em->persist(PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(50, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->withCreatedAt(new \DateTimeImmutable('2025-03-06'))
            ->assemble());
        $em->persist(PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(999, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->withCreatedAt(new \DateTimeImmutable('2025-02-05'))
            ->assemble());

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()
                ->withSchedule(new \DateTimeImmutable('2025-03-20 10:00:00'))
                ->withCapacity(10)
                ->assemble())
            ->withStatus('active')
            ->assemble();
        $em->persist($lesson);

        for ($i = 0; $i < 3; $i++) {
            $attendee = UserAssembler::new()->assemble();
            $em->persist($attendee);
            $em->persist(BookingAssembler::new()
                ->withUser($attendee)
                ->withLessons($lesson)
                ->withStatus('active')
                ->withCreatedAt(new \DateTimeImmutable('2025-01-01'))
                ->assemble());
        }

        $em->flush();

        $component = $this->createLiveComponent(name: AdminKpiStatsComponent::class, client: $client);
        /** @var AdminKpiStatsComponent $adminKpiStatsComponent */
        $adminKpiStatsComponent = $component->component();
        $kpi = $adminKpiStatsComponent->getKpiStats();

        self::assertSame(1, $kpi['bookingsCount']);
        self::assertTrue($kpi['revenue']->isEqualTo(Money::of(100, 'PLN')));
        self::assertSame('30%', $kpi['occupancyRate']);
        self::assertSame('Marzec', $kpi['monthLabel']);
    }
}
