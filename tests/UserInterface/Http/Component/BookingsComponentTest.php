<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\User;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\BookingsComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class BookingsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    /**
     * Regression test: a carnet booking with one past lesson and one future
     * lesson stays STATUS_ACTIVE (it only becomes STATUS_PAST once every
     * lesson is done), so the Past tab must not rely on the booking's own
     * status to decide what to show — it has to look at each lesson.
     */
    public function testPastTabShowsCarnetLessonEvenWhenBookingStillActive(): void
    {
        Clock::set(new MockClock('2026-08-08 12:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $pastLesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Zajecia przeszle w karnecie')
                    ->withTitle('Zajecia przeszle w karnecie')
                    ->assemble(),
            )
            ->withSchedule(new \DateTimeImmutable('2026-08-01 09:00:00'))
            ->assemble();
        $futureLesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Zajecia przyszle w karnecie')
                    ->withTitle('Zajecia przyszle w karnecie')
                    ->assemble(),
            )
            ->withSchedule(new \DateTimeImmutable('2026-08-15 09:00:00'))
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($pastLesson, $futureLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $em->persist($user);
        $em->persist($pastLesson);
        $em->persist($futureLesson);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: BookingsComponent::class,
            data: [
                'activeTab' => 'past',
            ],
            client: $client,
        );

        $html = (string) $component->render();

        static::assertStringContainsString('Zajecia przeszle w karnecie', $html);
        static::assertStringNotContainsString('Zajecia przyszle w karnecie', $html);
    }

    /**
     * Regression test: cancelling a single lesson within a multi-lesson
     * booking keeps the booking itself STATUS_ACTIVE, so the Cancelled tab
     * must not filter by the booking's own status either.
     */
    public function testCancelledTabShowsSingleCancelledLessonFromActiveCarnet(): void
    {
        Clock::set(new MockClock('2026-08-08 12:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->withName('Jan Testowy')->assemble();
        $cancelledLesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Zajecia odwolane w karnecie')
                    ->withTitle('Zajecia odwolane w karnecie')
                    ->assemble(),
            )
            ->withSchedule(new \DateTimeImmutable('2026-08-20 09:00:00'))
            ->assemble();
        $activeLesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Zajecia aktywne w karnecie')
                    ->withTitle('Zajecia aktywne w karnecie')
                    ->assemble(),
            )
            ->withSchedule(new \DateTimeImmutable('2026-08-25 09:00:00'))
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($cancelledLesson, $activeLesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $em->persist($user);
        $em->persist($cancelledLesson);
        $em->persist($activeLesson);
        $em->persist($booking);
        $em->flush();

        $booking->cancelLesson($cancelledLesson->getId()->toRfc4122(), 'Zmiana planow', $user);
        $em->flush();
        $em->clear();

        $reloadedUser = $em->getRepository(User::class)->find($user->getId());
        static::assertNotNull($reloadedUser);
        $client->loginUser($reloadedUser);

        $component = $this->createLiveComponent(
            name: BookingsComponent::class,
            data: [
                'activeTab' => 'cancelled',
            ],
            client: $client,
        );

        $html = (string) $component->render();

        static::assertStringContainsString('Zajecia odwolane w karnecie', $html);
        static::assertStringNotContainsString('Zajecia aktywne w karnecie', $html);
        // Attribution: who cancelled it and when.
        static::assertStringContainsString('Jan Testowy', $html);
    }

    /**
     * Regression test for the auto-cancellation attribution branch added in
     * 12190de: when a lesson was cancelled with no attributed user
     * (cancelledBy null) but a reason is present — the signature of the
     * hourly expiry sweep, see CheckExpiredBookingsHandler — the Cancelled
     * tab must show the "cancelled automatically" message instead of
     * silently falling back to "cancelled by {name}" with an empty name.
     */
    public function testCancelledTabShowsAutomaticCancellationReasonWhenNoUserAttributed(): void
    {
        Clock::set(new MockClock('2026-08-08 12:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Zajecia anulowane automatycznie')->assemble())
            ->withSchedule(new \DateTimeImmutable('2026-08-20 09:00:00'))
            ->assemble();

        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $em->persist($user);
        $em->persist($lesson);
        $em->persist($booking);
        $em->flush();

        // No $cancelledBy: mirrors what CheckExpiredBookingsHandler does for
        // the automatic expiry sweep.
        $booking->cancel(null, 'Rezerwacja anulowana automatycznie — brak płatności w wymaganym terminie');
        $em->flush();
        $em->clear();

        $reloadedUser = $em->getRepository(User::class)->find($user->getId());
        static::assertNotNull($reloadedUser);
        $client->loginUser($reloadedUser);

        $component = $this->createLiveComponent(
            name: BookingsComponent::class,
            data: [
                'activeTab' => 'cancelled',
            ],
            client: $client,
        );

        $html = (string) $component->render();

        static::assertStringContainsString('Zajecia anulowane automatycznie', $html);
        static::assertStringContainsString('Anulowane automatycznie', $html);
        static::assertStringContainsString('brak płatności w wymaganym terminie', $html);
    }

    public function testPaidAtIsShownForPaidBooking(): void
    {
        Clock::set(new MockClock('2026-08-08 12:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle('Zajecia oplacone')
                    ->withTitle('Zajecia oplacone')
                    ->assemble(),
            )
            ->withSchedule(new \DateTimeImmutable('2026-08-15 09:00:00'))
            ->assemble();
        $payment = PaymentAssembler::new()->withUser($user)->withAmount(Money::of(50, 'PLN'))->assemble();
        // Go through the real setStatus() transition (not the assembler's
        // reflection-based withStatus()) so paidAt gets populated exactly
        // like it would for a real payment.
        $payment->setStatus(Payment::STATUS_PAID);
        $booking = BookingAssembler::new()
            ->withUser($user)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();

        $em->persist($user);
        $em->persist($lesson);
        $em->persist($payment);
        $em->persist($booking);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: BookingsComponent::class,
            data: [
                'activeTab' => 'active',
            ],
            client: $client,
        );

        $html = (string) $component->render();

        static::assertStringContainsString('Zajecia oplacone', $html);
        static::assertMatchesRegularExpression('/Opłacono \d{2}\.\d{2}\.\d{4}/', $html);
    }

    public function testEmptyStateMessageMatchesSelectedTab(): void
    {
        $client = static::createClient();
        $user = UserAssembler::new()->assemble();
        static::getContainer()->get(EntityManagerInterface::class)->persist($user);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->loginUser($user);

        $pastComponent = $this->createLiveComponent(
            name: BookingsComponent::class,
            data: [
                'activeTab' => 'past',
            ],
            client: $client,
        );
        $pastHtml = (string) $pastComponent->render();
        static::assertStringContainsString('przeszłych', $pastHtml);
        static::assertStringNotContainsString('aktywnych rezerwacji', $pastHtml);

        $cancelledComponent = $this->createLiveComponent(
            name: BookingsComponent::class,
            data: [
                'activeTab' => 'cancelled',
            ],
            client: $client,
        );
        $cancelledHtml = (string) $cancelledComponent->render();
        static::assertStringContainsString('anulowanych', $cancelledHtml);
    }

    public function testActiveTabIsDeepLinkableViaUrlQuery(): void
    {
        $client = static::createClient();
        $user = UserAssembler::new()->assemble();
        static::getContainer()->get(EntityManagerInterface::class)->persist($user);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->loginUser($user);
        $client->request('GET', '/panel?activeTab=cancelled');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        static::assertStringContainsString('aria-selected="true"', $content);
        // The cancelled tab button must be the one carrying aria-selected="true".
        static::assertMatchesRegularExpression('/id="tab-cancelled"[^>]*aria-selected="true"/', $content);
    }
}
