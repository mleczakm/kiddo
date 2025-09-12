<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Payment;
use App\Entity\Booking;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use App\UserInterface\Http\Component\PaymentsListComponent;

#[Group('functional')]
final class PaymentsListComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testEmptyStateShowsMessage(): void
    {
        $component = $this->createLiveComponent(name: PaymentsListComponent::class, client: $this->client);
        $html = (string) $component->render();

        self::assertStringContainsString('Płatności', $html); // header
        self::assertStringContainsString('Brak płatności w wybranym tygodniu', $html); // empty state
    }

    public function testDisplaysPaidPaymentWithLessonTitleAndStatus(): void
    {
        $user = UserAssembler::new()->withEmail('paid@example.com')->withName('Paid User')->assemble();

        $weekStart = new \DateTimeImmutable('2025-01-06'); // Monday
        $lessonDate = $weekStart->modify('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($lessonDate)->withCapacity(5)->assemble())
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus('pending')
            ->withCreatedAt($weekStart->modify('+2 days'))
            ->assemble();
        // Mark as paid to set paidAt
        $payment->setStatus(Payment::STATUS_PAID);

        // Link a transfer so amountPaid > 0
        $transfer = TransferAssembler::new()->withAmount('100,00')->withTransferredAt(
            $weekStart->modify('+2 days')
        )->assemble();
        $this->em->persist($transfer);

        $payment->addTransfer($transfer);

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $payment->addBooking($booking);

        $this->em->persist($user);
        $this->em->persist($lesson);
        $this->em->persist($payment);
        $this->em->persist($booking);
        $this->em->flush();

        $component = $this->createLiveComponent(name: PaymentsListComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        self::assertStringContainsString('Default Title', $html);
        self::assertStringContainsString('Paid User', $html);
        // Status badge (Polish translation of paid)
        self::assertStringContainsString('Opłacone', $html);
    }

    public function testDisplaysPendingPaymentShowsPendingStatus(): void
    {
        $user = UserAssembler::new()->withEmail('pending@example.com')->withName('Pending User')->assemble();

        $weekStart = new \DateTimeImmutable('2025-01-13');
        $lessonDate = $weekStart->modify('+1 day');
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($lessonDate)->withCapacity(3)->assemble())
            ->withTitle('Pending Workshop')
            ->assemble();

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of('50.00', 'PLN'))
            ->withStatus('pending')
            ->withCreatedAt($weekStart->modify('+2 days'))
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $payment->addBooking($booking);

        $this->em->persist($user);
        $this->em->persist($lesson);
        $this->em->persist($payment);
        $this->em->persist($booking);
        $this->em->flush();

        $component = $this->createLiveComponent(name: PaymentsListComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        self::assertStringContainsString('Pending Workshop', $html);
        self::assertStringContainsString('Pending User', $html);
        // Status badge (Polish translation for pending: Oczekuje)
        self::assertStringContainsString('Oczekuje', $html);
    }

    public function testWeekFilteringShowsOnlyPaymentsInSelectedWeek(): void
    {
        $user = UserAssembler::new()->withEmail('filter@example.com')->withName('Filter User')->assemble();

        $firstWeek = new \DateTimeImmutable('2025-02-03');
        $secondWeek = $firstWeek->modify('+7 days');

        $lesson1 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($firstWeek->modify('+1 day'))->assemble())
            ->withTitle('Week1 Lesson')
            ->assemble();
        $payment1 = PaymentAssembler::new()->withUser($user)->withAmount(Money::of('10.00', 'PLN'))->withCreatedAt(
            $firstWeek->modify('+2 days')
        )->assemble();
        $payment1->setStatus(Payment::STATUS_PAID);
        $booking1 = BookingAssembler::new()->withUser($user)->withPayment($payment1)->withLessons($lesson1)->withStatus(
            Booking::STATUS_ACTIVE
        )->assemble();

        $lesson2 = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withSchedule($secondWeek->modify('+1 day'))->assemble())
            ->withTitle('Week2 Lesson')
            ->assemble();
        $payment2 = PaymentAssembler::new()->withUser($user)->withAmount(Money::of('20.00', 'PLN'))->withCreatedAt(
            $secondWeek->modify('+2 days')
        )->assemble();
        $payment2->setStatus(Payment::STATUS_PAID);
        $booking2 = BookingAssembler::new()->withUser($user)->withPayment($payment2)->withLessons($lesson2)->withStatus(
            Booking::STATUS_ACTIVE
        )->assemble();

        $payment1->addBooking($booking1);
        $payment2->addBooking($booking2);

        $this->em->persist($user);
        $this->em->persist($lesson1);
        $this->em->persist($payment1);
        $this->em->persist($booking1);
        $this->em->persist($lesson2);
        $this->em->persist($payment2);
        $this->em->persist($booking2);
        $this->em->flush();

        // First week should include Week1 Lesson, not Week2 Lesson
        $component1 = $this->createLiveComponent(PaymentsListComponent::class, [
            'week' => $firstWeek->format('Y-m-d'),
        ], $this->client);
        $html1 = (string) $component1->render();
        self::assertStringContainsString('Week1 Lesson', $html1);
        self::assertStringNotContainsString('Week2 Lesson', $html1);

        // Second week should include Week2 Lesson, not Week1 Lesson
        $component2 = $this->createLiveComponent(name: PaymentsListComponent::class, data: [
            'week' => $secondWeek->format('Y-m-d'),
        ], client: $this->client);
        $html2 = (string) $component2->render();
        self::assertStringContainsString('Week2 Lesson', $html2);
        self::assertStringNotContainsString('Week1 Lesson', $html2);
    }
}
