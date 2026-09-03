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
use App\UserInterface\Http\Component\OutstandingPaymentsComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class OutstandingPaymentsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testShowsPendingPaymentWithPayButton(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Zajecia z platnoscia')->assemble())
            ->withSchedule(new \DateTimeImmutable('+3 days'))
            ->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(120, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();

        $em->persist($user);
        $em->persist($lesson);
        $em->persist($payment);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $html = (string) $this->createLiveComponent(
            name: OutstandingPaymentsComponent::class,
            client: $client,
        )->render();

        static::assertStringContainsString('data-live-action-param="pay"', $html);
        static::assertStringContainsString((string) $payment->getId(), $html);
    }

    public function testRendersNothingWhenAllPaid(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(120, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();

        $em->persist($user);
        $em->persist($payment);
        $em->flush();

        $client->loginUser($user);

        $html = (string) $this->createLiveComponent(
            name: OutstandingPaymentsComponent::class,
            client: $client,
        )->render();

        static::assertStringNotContainsString('data-live-action-param="pay"', $html);
    }

    public function testPayGeneratesPaymentCodeAndShowsInstructions(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Zajecia do oplacenia')->assemble())
            ->withSchedule(new \DateTimeImmutable('+5 days'))
            ->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(90, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_PENDING)
            ->assemble();

        $em->persist($user);
        $em->persist($lesson);
        $em->persist($payment);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(name: OutstandingPaymentsComponent::class, client: $client);

        $rendered = $component->call('pay', ['paymentId' => (string) $payment->getId()]);
        $html = (string) $rendered->render();

        static::assertStringContainsString('data-poll', $html);

        $em->clear();
        $reloaded = $em->getRepository(Payment::class)->find($payment->getId());
        static::assertNotNull($reloaded);
        static::assertNotNull($reloaded->getPaymentCode());
    }
}
