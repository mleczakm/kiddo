<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\BookingOccurrence;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\BookingCancellationModal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class BookingCancellationModalTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testOtherReasonTabCanBeSelectedAndCancellationReasonIsStored(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()
            ->withTitle('Sensoplastyka')
            ->withSchedule(Clock::get()->now()->modify('+2 days'))
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

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: BookingCancellationModal::class,
            data: [
                'booking' => $booking,
                'lesson' => $lesson,
                'modalOpened' => true,
            ],
            client: $client,
        );

        $html = (string) $component->render();
        static::assertStringContainsString('data-live-action-param="selectTab"', $html);
        static::assertStringContainsString('data-live-option-param="cancel"', $html);
        static::assertStringNotContainsString('data-live-index-param', $html);

        // Mirrors the arguments sent when the user clicks the "Inne" tab.
        // The action previously required an unused $_index argument that the
        // browser did not send under that name, so argument resolution failed.
        $component->call('selectTab', [
            'option' => 'cancel',
        ]);
        $component->set('cancellationReason', 'Zmiana planów rodzinnych');
        $component->call('processCancellation', [
            'type' => 'cancel',
        ]);

        $em->clear();
        /** @var Booking $reloaded */
        $reloaded = $em->getRepository(Booking::class)->find($booking->getId());
        $occurrence = $reloaded->findOccurrence($lesson->getId());

        static::assertNotNull($occurrence);
        static::assertSame(BookingOccurrence::STATUS_CANCELLED, $occurrence->getStatus());
        static::assertSame('Zmiana planów rodzinnych', $occurrence->getCancellationReason());
    }
}
