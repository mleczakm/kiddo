<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\BillingComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class BillingComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testListsPaidAndUnpaidPaymentsWithOutstandingTotal(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();

        $paidLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Zajecia oplacone')->assemble())
            ->withSchedule(new \DateTimeImmutable('-10 days'))
            ->assemble();
        $paid = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(100, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $paidBooking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($paid)
            ->withLessons($paidLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $dueLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Zajecia do zaplaty')->assemble())
            ->withSchedule(new \DateTimeImmutable('+10 days'))
            ->assemble();
        $due = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(140, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $dueBooking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($due)
            ->withLessons($dueLesson)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();

        foreach ([$user, $paidLesson, $paid, $paidBooking, $dueLesson, $due, $dueBooking] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $em->clear();

        $client->loginUser($user);

        $html = (string) $this->createLiveComponent(name: BillingComponent::class, client: $client)->render();

        static::assertStringContainsString('Zajecia oplacone', $html);
        static::assertStringContainsString('Zajecia do zaplaty', $html);
        // The outstanding-total card is shown, carrying the pending amount.
        static::assertStringContainsString('Do zapłaty', $html);
        static::assertStringContainsString('140', $html);
    }

    public function testOnlyUnpaidFilterHidesPaidPayments(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();

        $paidLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Rozliczone zajecia')->assemble())
            ->withSchedule(new \DateTimeImmutable('-5 days'))
            ->assemble();
        $paid = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(80, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $paidBooking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($paid)
            ->withLessons($paidLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        foreach ([$user, $paidLesson, $paid, $paidBooking] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $em->clear();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: BillingComponent::class,
            data: ['onlyUnpaid' => true],
            client: $client,
        );

        static::assertStringNotContainsString('Rozliczone zajecia', (string) $component->render());
    }

    public function testDoesNotLeakOtherUsersPayments(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $other = UserAssembler::new()->withEmail('other-billing@test.com')->assemble();

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Cudza platnosc')->assemble())
            ->withSchedule(new \DateTimeImmutable('-2 days'))
            ->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($other)
            ->withAmount(Money::of(50, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $booking = BookingAssembler::new()
            ->withUser($other)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        foreach ([$user, $other, $lesson, $payment, $booking] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $em->clear();

        $client->loginUser($user);

        static::assertStringNotContainsString(
            'Cudza platnosc',
            (string) $this->createLiveComponent(name: BillingComponent::class, client: $client)->render(),
        );
    }
}
