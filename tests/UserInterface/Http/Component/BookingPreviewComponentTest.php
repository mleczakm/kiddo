<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\WorkshopType;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\BookingPreviewComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class BookingPreviewComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testPayButtonEmitsResumePaymentUpToParent(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Yoga')->withTitle('Yoga')->assemble())
            ->withSchedule(new \DateTimeImmutable('2024-02-21 09:00:00'))
            ->assemble();
        $lesson->setSeries($series);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withAmount(Money::of(50, 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $paymentCode = new PaymentCode($payment, 'QP12');
        $booking = BookingAssembler::new()->withUser($user)->withPayment($payment)->withLessons($lesson)->assemble();

        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->persist($payment);
        $em->persist($paymentCode);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: BookingPreviewComponent::class,
            data: [
                'lesson' => $lesson,
            ],
            client: $client,
        );

        $html = (string) $component->render();

        static::assertStringContainsString('data-action="live#emitUp"', $html);
        static::assertStringContainsString('data-live-event-param="resumePayment"', $html);
        // kebab-case is required: Stimulus maps booking-id → bookingId for #[LiveArg]
        static::assertStringContainsString('data-live-booking-id-param="' . $booking->getId() . '"', $html);
        static::assertStringNotContainsString('data-live-bookingId-param=', $html);
        static::assertStringNotContainsString('data-live-action-param="resumePayment"', $html);
        static::assertStringContainsString('Zapłać', $html);
    }
}
