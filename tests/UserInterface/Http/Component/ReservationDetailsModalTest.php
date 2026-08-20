<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\RefundRequest;
use App\Entity\WorkshopType;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\ReservationDetailsModal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class ReservationDetailsModalTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRendersAsARegisteredLiveComponentInsteadOfAnonymousFallback(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(name: ReservationDetailsModal::class, client: $this->client);

        // If the component isn't correctly registered under this name/class, Twig's
        // ComponentFactory silently falls back to an AnonymousComponent, and rendering
        // `this.modalOpened` blows up with a public-property-access error.
        $rendered = (string) $component->render();

        static::assertInstanceOf(ReservationDetailsModal::class, $component->component());
        static::assertStringNotContainsString('Szczegóły rezerwacji', $rendered);
    }

    public function testOpenLoadsTheBookingAndRendersItsDetails(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->withName('Anna Kowalska')->assemble();
        $this->em->persist($customer);

        $lesson = LessonAssembler::new()
            ->withTitle('Sensoplastyka')
            ->withSchedule(Clock::get()->now()->modify('+1 day'))
            ->assemble();

        $series = SeriesAssembler::new()->withType(WorkshopType::WEEKLY)->assemble();
        $lesson->setSeries($series);

        $this->em->persist($series);
        $this->em->persist($lesson);

        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $this->em->persist($booking);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(name: ReservationDetailsModal::class, client: $this->client);
        $component->call('open', [
            'bookingId' => (string) $booking->getId(),
        ]);

        /** @var ReservationDetailsModal $modal */
        $modal = $component->component();
        static::assertTrue($modal->modalOpened);
        static::assertSame((string) $booking->getId(), (string) $modal->getBooking()?->getId());

        // The lesson belongs to a series, exercising the "· <series type>" caption
        // that previously crashed on `bookedLesson.series.name` (Series has no such
        // property/getter).
        $rendered = (string) $component->render();
        static::assertStringContainsString('Szczegóły rezerwacji', $rendered);
        static::assertStringContainsString('Anna Kowalska', $rendered);
        static::assertStringContainsString('Sensoplastyka', $rendered);
        static::assertStringContainsString('Cotygodniowa', $rendered);
    }

    public function testBackButtonAfterSelectingAnActionDoesNotCrash(): void
    {
        // Regression test: the "Wróć"/"Anuluj rezerwację"/"Anuluj ze zwrotem" buttons
        // used to render `data-live-action-value-param` instead of the LiveArg-matching
        // `data-live-value-param`, so clicking them never sent the `value` argument at
        // all. selectAction(#[LiveArg] string $value) has no default, so the browser hit
        // "Could not resolve argument $value of ...selectAction()" every time.
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->assemble();
        $this->em->persist($customer);

        $lesson = LessonAssembler::new()
            ->withTitle('Sensoplastyka')
            ->withSchedule(Clock::get()->now()->modify('+1 day'))
            ->assemble();
        $this->em->persist($lesson);

        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $this->em->persist($booking);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(name: ReservationDetailsModal::class, client: $this->client);
        $component->call('open', [
            'bookingId' => (string) $booking->getId(),
        ]);

        $beforeSelecting = (string) $component->render();
        static::assertStringContainsString('data-live-value-param="cancel"', $beforeSelecting);
        static::assertStringNotContainsString('data-live-action-value-param', $beforeSelecting);

        $component->call('selectAction', [
            'value' => 'cancel',
        ]);

        $afterSelecting = (string) $component->render();
        static::assertStringContainsString('data-live-value-param=""', $afterSelecting);
        static::assertStringNotContainsString('data-live-action-value-param', $afterSelecting);

        // Exercises the exact call the "Wróć" button makes - must not throw.
        $component->call('selectAction', [
            'value' => '',
        ]);

        /** @var ReservationDetailsModal $modal */
        $modal = $component->component();
        static::assertSame('', $modal->action);
    }

    public function testAdminCanMarkRequestedRefundAsRefundedWithNote(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $customer = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->withSchedule(Clock::get()->now()->modify('+1 day'))->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($customer)
            ->withStatus(Payment::STATUS_REFUND_REQUESTED)
            ->assemble();
        $booking = BookingAssembler::new()
            ->withUser($customer)
            ->withPayment($payment)
            ->withLessons($lesson)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $refundRequest = new RefundRequest(
            payment: $payment,
            booking: $booking,
            lesson: $lesson,
            requestedAmount: $payment->getAmount(),
            requestedBy: $customer,
            requestMessage: null,
        );

        foreach ([$admin, $customer, $lesson, $payment, $booking, $refundRequest] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(name: ReservationDetailsModal::class, client: $this->client);
        $component->call('open', [
            'bookingId' => (string) $booking->getId(),
        ]);
        $component->set('paymentNote', 'Zwrot bankowy nr 123');
        $component->call('changePaymentStatus', [
            'transition' => Payment::TRANSITION_REFUND,
        ]);

        $this->em->clear();
        $updatedPayment = $this->em->find(Payment::class, $payment->getId());
        static::assertInstanceOf(Payment::class, $updatedPayment);
        static::assertSame(Payment::STATUS_REFUNDED, $updatedPayment->getStatus());
        static::assertSame('Zwrot bankowy nr 123', $updatedPayment->getStatusNote());
        static::assertSame($admin->getId(), $updatedPayment->getStatusChangedBy()?->getId());
    }
}
