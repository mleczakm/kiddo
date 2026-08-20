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
use App\Application\Service\Pricing\PriceQuoter;
use App\Application\Service\Pricing\ShadowPricingEvaluator;
use App\Application\UseCase\PlaceSingleReservation;
use App\Application\UseCase\PriceQuoteMismatchException;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use App\Entity\TicketType;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Stage 8 of the commerce rollout plan: dynamic_pricing is enabled by
 * default, so these tests use the real container-wired PlaceSingleReservation
 * rather than constructing it manually (contrast
 * PlaceSingleReservationOrderDualWriteTest, which needs a mocked
 * FeatureManager specifically to force commerce_order_write on).
 */
#[Group('functional')]
final class PlaceSingleReservationDynamicPricingTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private MessageBusInterface $messageBus;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var MessageBusInterface $messageBus */
        $messageBus = $container->get(MessageBusInterface::class);
        $this->messageBus = $messageBus;
    }

    public function testChargesTheCurrentPriceWhenNoExpectedHashIsSupplied(): void
    {
        // Callers outside the modal (e.g. the chat booking tool) don't
        // participate in the quote/reconfirm flow - they just get charged
        // whatever the price currently resolves to.
        [$user, $lesson] = $this->setUpUserAndLesson();

        $this->messageBus->dispatch(new AddBooking(
            userId: $user->getId() ?? throw new \LogicException('missing user id'),
            lessonId: (string) $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            childId: null,
            paymentCode: 'DYN1',
            expectedQuoteHash: null,
        ));

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        $booking = $bookingRepository->findOneBy(['user' => $user]);
        static::assertInstanceOf(Booking::class, $booking);
        static::assertSame('5000', (string) $booking->getPayment()?->getAmount()->getMinorAmount()->toInt());
    }

    public function testSucceedsWhenTheSuppliedHashMatchesTheCurrentQuote(): void
    {
        [$user, $lesson] = $this->setUpUserAndLesson();

        /** @var PriceQuoter $quoter */
        $quoter = self::getContainer()->get(PriceQuoter::class);
        $userId = $user->getId();
        static::assertNotNull($userId);
        $ticketOption = $lesson->getMatchingTicketOption(TicketType::ONE_TIME->value);
        $quote = $quoter->quote($userId, $lesson, TicketType::ONE_TIME->value, $ticketOption->price);

        $this->messageBus->dispatch(new AddBooking(
            userId: $userId,
            lessonId: (string) $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            childId: null,
            paymentCode: 'DYN2',
            expectedQuoteHash: $quote->quoteHash,
        ));

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        $booking = $bookingRepository->findOneBy(['user' => $user]);
        static::assertInstanceOf(Booking::class, $booking);
    }

    public function testRejectsAStaleHashWithoutPlacingTheOrder(): void
    {
        [$user, $lesson] = $this->setUpUserAndLesson();

        try {
            $this->messageBus->dispatch(new AddBooking(
                userId: $user->getId() ?? throw new \LogicException('missing user id'),
                lessonId: (string) $lesson->getId(),
                ticketType: TicketType::ONE_TIME->value,
                childId: null,
                paymentCode: 'DYN3',
                expectedQuoteHash: 'this-hash-will-never-match-the-current-quote',
            ));
            static::fail('Expected a PriceQuoteMismatchException.');
        } catch (HandlerFailedException $e) {
            // getWrappedExceptions() is keyed by handler name, not a sequential index.
            $wrapped = current($e->getWrappedExceptions());
            static::assertInstanceOf(PriceQuoteMismatchException::class, $wrapped);
            static::assertSame(5_000, $wrapped->currentQuote->finalPriceMinor);
        }

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        static::assertNull(
            $bookingRepository->findOneBy(['user' => $user]),
            'No booking should be created when the quote is stale',
        );
    }

    public function testStoresTheQuoteSnapshotOnTheOrderLineWhenOrderWriteIsAlsoEnabled(): void
    {
        [$user, $lesson] = $this->setUpUserAndLesson();

        // dynamic_pricing is enabled globally (see config/packages/novaway_feature_flag.yaml),
        // but commerce_order_write defaults off - force both on for this one call, matching
        // PlaceSingleReservationOrderDualWriteTest's pattern.
        $bothEnabled = $this->createMock(FeatureManager::class);
        $bothEnabled->method('isEnabled')->willReturn(true);

        $container = self::getContainer();
        /** @var UserRepositoryInterface $userRepository */
        $userRepository = $container->get(UserRepositoryInterface::class);
        /** @var LessonRepositoryInterface $lessonRepository */
        $lessonRepository = $container->get(LessonRepositoryInterface::class);
        /** @var ChildRepositoryInterface $childRepository */
        $childRepository = $container->get(ChildRepositoryInterface::class);
        /** @var BookingFactory $bookingFactory */
        $bookingFactory = $container->get(BookingFactory::class);
        /** @var InAppNotificationService $inAppNotifications */
        $inAppNotifications = $container->get(InAppNotificationService::class);
        /** @var LessonInstructorResolver $instructorResolver */
        $instructorResolver = $container->get(LessonInstructorResolver::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $container->get(UrlGeneratorInterface::class);
        /** @var TranslatorInterface $translator */
        $translator = $container->get(TranslatorInterface::class);
        /** @var ShadowPricingEvaluator $shadowPricing */
        $shadowPricing = $container->get(ShadowPricingEvaluator::class);
        /** @var PriceQuoter $priceQuoter */
        $priceQuoter = $container->get(PriceQuoter::class);
        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        $placeSingleReservation = new PlaceSingleReservation(
            $this->em,
            $bus,
            $userRepository,
            $lessonRepository,
            $childRepository,
            $bookingFactory,
            $inAppNotifications,
            $instructorResolver,
            $urlGenerator,
            $translator,
            $bothEnabled,
            new FastTrackOrderFactory(),
            $shadowPricing,
            $priceQuoter,
        );

        $placeSingleReservation(new AddBooking(
            userId: $user->getId() ?? throw new \LogicException('missing user id'),
            lessonId: (string) $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            childId: null,
            paymentCode: 'DYN4',
            expectedQuoteHash: null,
        ));

        $this->em->clear();
        $orders = $this->em->getRepository(CustomerOrder::class)->findAll();
        static::assertCount(1, $orders);
        $lines = $this->em->getRepository(OrderLine::class)->findAll();
        static::assertCount(1, $lines);
        $line = $lines[0];
        static::assertNotNull($line->getPricingQuoteHash());
        $snapshot = $line->getPricingSnapshotJson();
        static::assertIsArray($snapshot);
        static::assertArrayHasKey('adjustments', $snapshot);
        static::assertArrayHasKey('rejectedRuleReasons', $snapshot);
    }

    /**
     * @return array{0: \App\Entity\User, 1: \App\Entity\Lesson}
     */
    private function setUpUserAndLesson(): array
    {
        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($user);
        $this->em->persist($lesson);
        $this->em->flush();

        return [$user, $lesson];
    }
}
