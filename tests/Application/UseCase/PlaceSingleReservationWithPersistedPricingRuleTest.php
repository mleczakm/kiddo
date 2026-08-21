<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\Command\AddBooking;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Entity\Booking;
use App\Entity\TicketType;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Stage 9 of the commerce rollout plan: proves a PricingRule created through
 * the admin CRUD actually flows through to a real booking's charged price -
 * not just that PriceQuoter's repository query works in isolation (see
 * PricingRuleRepositoryTest), but that the whole path from "an admin saved a
 * rule" to "a customer gets charged the adjusted price" is wired correctly.
 */
#[Group('functional')]
final class PlaceSingleReservationWithPersistedPricingRuleTest extends KernelTestCase
{
    public function testAnActivePersistedRuleReducesTheChargedPrice(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);

        $rule = new PricingRule(
            id: new Ulid(),
            name: 'Stage 9 integration discount',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 1_000,
        );
        $em->persist($rule);
        $em->flush();

        $userId = $user->getId();
        static::assertNotNull($userId);

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);
        $bus->dispatch(new AddBooking(
            userId: $userId,
            lessonId: (string) $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            childId: null,
            paymentCode: 'RULE',
            expectedQuoteHash: null,
        ));

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = $container->get(BookingRepository::class);
        $booking = $bookingRepository->findOneBy(['user' => $user]);
        static::assertInstanceOf(Booking::class, $booking);
        // The default LessonAssembler ticket is 50.00 PLN (5000 minor) - a 1000
        // minor fixed discount should charge exactly 4000.
        static::assertSame(4_000, $booking->getPayment()?->getAmount()->getMinorAmount()->toInt());
    }
}
