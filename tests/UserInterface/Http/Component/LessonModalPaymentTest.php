<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\Booking;
use App\Entity\WorkshopType;
use App\Repository\BookingRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\LessonModal;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class LessonModalPaymentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testCreatesBookingWhenWorkshopPageOpensModalDirectly(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()
            ->withPhone('501111111')
            ->assemble();
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::ONE_TIME)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Sensory')->assemble())
            ->withSchedule(new \DateTimeImmutable('2024-02-21 10:30:00'))
            ->assemble();
        $lesson->setSeries($series);

        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'termsAccepted' => true,
                'closeUrl' => '/warsztaty',
            ],
            client: $client,
        );

        $component->call('processPayment');

        /** @var LessonModal $lessonModal */
        $lessonModal = $component->component();
        self::assertSame('awaiting_payment', $lessonModal->paymentStatus);
        self::assertNotNull($lessonModal->paymentCode);

        /** @var BookingRepository $bookings */
        $bookings = static::getContainer()->get(BookingRepository::class);
        $booking = $bookings->findOneBy([
            'user' => $user,
        ]);
        self::assertInstanceOf(Booking::class, $booking);
        $bookedLesson = $booking->getLessons()
            ->first();
        self::assertNotFalse($bookedLesson);
        self::assertSame((string) $lesson->getId(), (string) $bookedLesson->getId());
        self::assertSame($lessonModal->paymentCode, $booking->getPayment()?->getPaymentCode()?->getCode());
    }

    public function testResumePaymentShowsExistingPaymentCode(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::ONE_TIME)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Sensory')
                    ->withTitle('Sensory')
                    ->assemble()
            )
            ->withSchedule(new \DateTimeImmutable('2024-02-21 10:30:00'))
            ->assemble();
        $lesson->setSeries($series);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(45, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $paymentCode = new PaymentCode($payment, 'AB12');
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->assemble();

        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->persist($payment);
        $em->persist($paymentCode);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'closeUrl' => '/warsztaty',
            ],
            client: $client,
        );

        $component->call('resumePayment', [
            'bookingId' => (string) $booking->getId(),
        ]);

        /** @var LessonModal $lessonModal */
        $lessonModal = $component->component();
        $this->assertSame('AB12', $lessonModal->paymentCode);
        $this->assertSame('awaiting_payment', $lessonModal->paymentStatus);
        $this->assertNotNull($lessonModal->getPaymentAmount());
        $this->assertSame((string) $booking->getId(), $lessonModal->resumedBookingId);
    }

    public function testResumePaymentViaEventFromBookingPreview(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::ONE_TIME)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Sensory')
                    ->withTitle('Sensory')
                    ->assemble()
            )
            ->withSchedule(new \DateTimeImmutable('2024-02-21 10:30:00'))
            ->assemble();
        $lesson->setSeries($series);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(45, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $paymentCode = new PaymentCode($payment, 'XY99');
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->assemble();

        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->persist($payment);
        $em->persist($paymentCode);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'closeUrl' => '/warsztaty',
            ],
            client: $client,
        );

        $html = (string) $component->render();
        $this->assertStringContainsString('data-action="live#emitUp"', $html);
        $this->assertStringContainsString('data-live-event-param="resumePayment"', $html);
        // Stimulus maps data-live-booking-id-param → bookingId (HTML attrs are case-insensitive,
        // so data-live-bookingId-param becomes bookingid and fails LiveArg resolution).
        $this->assertStringContainsString('data-live-booking-id-param="' . $booking->getId() . '"', $html);
        $this->assertStringNotContainsString('data-live-bookingId-param=', $html);

        $component->emit('resumePayment', [
            'bookingId' => (string) $booking->getId(),
        ]);

        /** @var LessonModal $lessonModal */
        $lessonModal = $component->component();
        $this->assertSame('XY99', $lessonModal->paymentCode);
        $this->assertSame('awaiting_payment', $lessonModal->paymentStatus);
        $this->assertSame((string) $booking->getId(), $lessonModal->resumedBookingId);
    }

    public function testResumePaymentRejectsMissingBookingIdArgument(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::ONE_TIME)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Sensory')
                    ->withTitle('Sensory')
                    ->assemble()
            )
            ->withSchedule(new \DateTimeImmutable('2024-02-21 10:30:00'))
            ->assemble();
        $lesson->setSeries($series);

        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($user);
        $client->catchExceptions(false);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'closeUrl' => '/warsztaty',
            ],
            client: $client,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Could not resolve argument \$bookingId/');

        // Mimics Stimulus receiving data-live-bookingId-param (HTML-lowercased to bookingid).
        $component->call('resumePayment', [
            'bookingid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
    }

    public function testRefreshPaymentStatusMarksPaidWhenTransferMatched(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::ONE_TIME)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new() ->withTitle('Music') ->withTitle('Music') ->assemble())
            ->withSchedule(new \DateTimeImmutable('2024-02-21 11:00:00'))
            ->assemble();
        $lesson->setSeries($series);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(45, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $paymentCode = new PaymentCode($payment, 'CD34');
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->assemble();

        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->persist($payment);
        $em->persist($paymentCode);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'closeUrl' => '/warsztaty',
            ],
            client: $client,
        );

        $component->call('resumePayment', [
            'bookingId' => (string) $booking->getId(),
        ]);

        /** @var LessonModal $awaiting */
        $awaiting = $component->component();
        $this->assertSame('awaiting_payment', $awaiting->paymentStatus);
        $this->assertNotNull($awaiting->watchedPaymentId);

        $paymentId = $awaiting->watchedPaymentId;
        $em->clear();
        $reloaded = $em->find(Payment::class, Ulid::fromString($paymentId));
        $this->assertNotNull($reloaded);
        $reloaded->setStatus(Payment::STATUS_PAID);
        $em->flush();
        $em->clear();

        $component->call('refreshPaymentStatus');
        /** @var LessonModal $paid */
        $paid = $component->component();
        $this->assertSame('paid', $paid->paymentStatus);
        $this->assertNull($paid->paymentCode);
    }

    public function testExistingBookingsAreVisibleForOwner(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::ONE_TIME)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new() ->withTitle('Art') ->withTitle('Art') ->assemble())
            ->withSchedule(new \DateTimeImmutable('2024-02-22 12:00:00'))
            ->assemble();
        $lesson->setSeries($series);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(45, 'PLN'))
            ->assemble();
        $paymentCode = new PaymentCode($payment, 'EF56');
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->assemble();

        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->persist($payment);
        $em->persist($paymentCode);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
                'closeUrl' => '/warsztaty',
            ],
            client: $client,
        );

        /** @var LessonModal $lessonModal */
        $lessonModal = $component->component();
        $existing = $lessonModal->getExistingBookings();
        $this->assertCount(1, $existing);
        $this->assertTrue($existing[0]->getId()->equals($booking->getId()));
        $this->assertStringContainsString('Istniejące rezerwacje', (string) $component->render());
        $this->assertStringContainsString('Zapłać', (string) $component->render());
    }
}
