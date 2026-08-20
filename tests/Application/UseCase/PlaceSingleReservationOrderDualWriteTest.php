<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase;

use App\Application\Command\AddBooking;
use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\BookingFactory;
use App\Application\Service\Commerce\FastTrackOrderFactory;
use App\Application\Service\InAppNotificationService;
use App\Application\Service\LessonInstructorResolver;
use App\Application\UseCase\PlaceSingleReservation;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\TicketType;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zenstruck\Mailer\Test\InteractsWithMailer;

/**
 * Stage 5 of the commerce rollout plan: PlaceSingleReservation dual-writes a
 * CustomerOrder/OrderLine behind the (disabled-by-default) commerce_order_write
 * flag. Nothing reads from these tables yet - this only proves the flag
 * genuinely gates the write, and that the write is correct when enabled.
 */
#[Group('functional')]
final class PlaceSingleReservationOrderDualWriteTest extends KernelTestCase
{
    use InteractsWithMailer;

    public function testNoOrderIsWrittenWhenTheFlagIsDisabled(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();

        /** @var PlaceSingleReservation $placeSingleReservation */
        $placeSingleReservation = self::getContainer()->get(PlaceSingleReservation::class);
        $userId = $user->getId();
        static::assertNotNull($userId);
        $placeSingleReservation(new AddBooking(
            userId: $userId,
            lessonId: (string) $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            childId: null,
            paymentCode: 'AB12',
        ));

        static::assertCount(0, $em->getRepository(CustomerOrder::class)->findAll());
        static::assertCount(0, $em->getRepository(OrderLine::class)->findAll());

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        $bookings = $bookingRepository->findBy(['user' => $userId]);
        static::assertCount(1, $bookings);
        $booking = $bookings[0];
        static::assertInstanceOf(Booking::class, $booking);
        static::assertNull($booking->getOrderLineId());
        static::assertNull($booking->getPayment()?->getOrderId());
    }

    public function testOrderAndLineAreWrittenAndLinkedWhenTheFlagIsEnabled(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->withTitle('Sensoplastyka')->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();

        $alwaysEnabled = $this->createMock(FeatureManager::class);
        $alwaysEnabled->method('isEnabled')->with('commerce_order_write')->willReturn(true);

        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);
        /** @var UserRepositoryInterface $userRepository */
        $userRepository = self::getContainer()->get(UserRepositoryInterface::class);
        /** @var LessonRepositoryInterface $lessonRepository */
        $lessonRepository = self::getContainer()->get(LessonRepositoryInterface::class);
        /** @var ChildRepositoryInterface $childRepository */
        $childRepository = self::getContainer()->get(ChildRepositoryInterface::class);
        /** @var BookingFactory $bookingFactory */
        $bookingFactory = self::getContainer()->get(BookingFactory::class);
        /** @var InAppNotificationService $inAppNotifications */
        $inAppNotifications = self::getContainer()->get(InAppNotificationService::class);
        /** @var LessonInstructorResolver $instructorResolver */
        $instructorResolver = self::getContainer()->get(LessonInstructorResolver::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = self::getContainer()->get(UrlGeneratorInterface::class);
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get(TranslatorInterface::class);

        $placeSingleReservation = new PlaceSingleReservation(
            $em,
            $bus,
            $userRepository,
            $lessonRepository,
            $childRepository,
            $bookingFactory,
            $inAppNotifications,
            $instructorResolver,
            $urlGenerator,
            $translator,
            $alwaysEnabled,
            new FastTrackOrderFactory(),
        );

        $userId = $user->getId();
        static::assertNotNull($userId);
        $placeSingleReservation(new AddBooking(
            userId: $userId,
            lessonId: (string) $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            childId: null,
            paymentCode: 'CD34',
        ));

        $em->clear();

        $orders = $em->getRepository(CustomerOrder::class)->findAll();
        static::assertCount(1, $orders);
        $order = $orders[0];
        static::assertSame(CustomerOrder::STATUS_PLACED, $order->getStatus());
        static::assertSame(CustomerOrder::SOURCE_FAST_TRACK, $order->getSource());
        static::assertSame($userId, $order->getCustomerId());

        $lines = $em->getRepository(OrderLine::class)->findAll();
        static::assertCount(1, $lines);
        $line = $lines[0];
        static::assertTrue($line->getOrderId()->equals($order->getId()));
        static::assertSame('Sensoplastyka', $line->getTitle());

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        $bookings = $bookingRepository->findBy(['user' => $userId]);
        static::assertCount(1, $bookings);
        $booking = $bookings[0];
        static::assertInstanceOf(Booking::class, $booking);
        static::assertNotNull($booking->getOrderLineId());
        static::assertTrue($booking->getOrderLineId()?->equals($line->getId()));

        $payment = $booking->getPayment();
        static::assertInstanceOf(Payment::class, $payment);
        static::assertNotNull($payment->getOrderId());
        static::assertTrue($payment->getOrderId()?->equals($order->getId()));
    }
}
