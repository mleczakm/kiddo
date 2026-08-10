<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\WorkshopType;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
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

        self::assertInstanceOf(ReservationDetailsModal::class, $component->component());
        self::assertStringNotContainsString('Szczegóły rezerwacji', $rendered);
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

        $series = SeriesAssembler::new()
            ->withType(WorkshopType::WEEKLY)
            ->assemble();
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
        self::assertTrue($modal->modalOpened);
        self::assertSame((string) $booking->getId(), (string) $modal->getBooking()?->getId());

        // The lesson belongs to a series, exercising the "· <series type>" caption
        // that previously crashed on `bookedLesson.series.name` (Series has no such
        // property/getter).
        $rendered = (string) $component->render();
        self::assertStringContainsString('Szczegóły rezerwacji', $rendered);
        self::assertStringContainsString('Anna Kowalska', $rendered);
        self::assertStringContainsString('Sensoplastyka', $rendered);
        self::assertStringContainsString('Cotygodniowa', $rendered);
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
        self::assertStringContainsString('data-live-value-param="cancel"', $beforeSelecting);
        self::assertStringNotContainsString('data-live-action-value-param', $beforeSelecting);

        $component->call('selectAction', [
            'value' => 'cancel',
        ]);

        $afterSelecting = (string) $component->render();
        self::assertStringContainsString('data-live-value-param=""', $afterSelecting);
        self::assertStringNotContainsString('data-live-action-value-param', $afterSelecting);

        // Exercises the exact call the "Wróć" button makes - must not throw.
        $component->call('selectAction', [
            'value' => '',
        ]);

        /** @var ReservationDetailsModal $modal */
        $modal = $component->component();
        self::assertSame('', $modal->action);
    }
}
