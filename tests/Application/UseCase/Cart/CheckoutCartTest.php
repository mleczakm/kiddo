<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\Service\Commerce\CapacityExceededException;
use App\Application\Service\Commerce\PromotionLimitExceededException;
use App\Application\UseCase\Cart\AddCartItem;
use App\Application\UseCase\Cart\ApplyPromotionCode;
use App\Application\UseCase\Cart\CheckoutCart;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Domain\Commerce\Pricing\PromotionRedemption;
use App\Entity\Booking;
use App\Entity\TicketType;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class CheckoutCartTest extends KernelTestCase
{
    public function testCheckingOutPlacesAnOrderWithOneLinePerItemAndOneSharedPayment(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var CheckoutCart $checkoutCart */
        $checkoutCart = self::getContainer()->get(CheckoutCart::class);

        $user = UserAssembler::new()->assemble();
        $lessonA = LessonAssembler::new()->withTitle('Sensoplastyka')->assemble();
        $lessonB = LessonAssembler::new()->withTitle('Origami')->assemble();
        $em->persist($user);
        $em->persist($lessonA);
        $em->persist($lessonB);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();
        $addCartItem($cart->id, (string) $lessonA->getId(), TicketType::ONE_TIME->value, null, $userId);
        $addCartItem($cart->id, (string) $lessonB->getId(), TicketType::ONE_TIME->value, null, $userId);

        $order = $checkoutCart($cart->id, $userId, 'CHKT');

        static::assertSame(CustomerOrder::SOURCE_CART, $order->getSource());
        static::assertSame(10_000, $order->getTotalMinor());
        static::assertSame(Cart::STATUS_CONVERTED, $cart->status);
        static::assertTrue($cart->convertedOrderId?->equals($order->getId()));

        $em->clear();
        $lines = $em->getRepository(OrderLine::class)->findBy(['orderId' => $order->getId()]);
        static::assertCount(2, $lines);

        /** @var BookingRepository $bookingRepository */
        $bookingRepository = self::getContainer()->get(BookingRepository::class);
        $bookings = $bookingRepository->findBy(['user' => $userId]);
        static::assertCount(2, $bookings);
        $paymentIds = array_unique(array_map(
            static fn(Booking $b): string => (string) $b->getPayment()?->getId(),
            $bookings,
        ));
        static::assertCount(1, $paymentIds, 'Both bookings should share one payment');
        static::assertSame('CHKT', $bookings[0]->getPayment()?->getPaymentCode()?->getCode());
    }

    public function testCheckingOutTwiceIsIdempotentAndReturnsTheSameOrder(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var CheckoutCart $checkoutCart */
        $checkoutCart = self::getContainer()->get(CheckoutCart::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();
        $addCartItem($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);

        $first = $checkoutCart($cart->id, $userId, 'IDEM');
        $second = $checkoutCart($cart->id, $userId);

        static::assertTrue($first->getId()->equals($second->getId()));
        static::assertCount(1, $em->getRepository(CustomerOrder::class)->findAll());
    }

    public function testCheckingOutAnEmptyCartThrows(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var CheckoutCart $checkoutCart */
        $checkoutCart = self::getContainer()->get(CheckoutCart::class);

        $user = UserAssembler::new()->assemble();
        $em->persist($user);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();

        $this->expectException(\LogicException::class);
        $checkoutCart($cart->id, $userId);
    }

    public function testCannotCheckOutSomeoneElsesCart(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var CheckoutCart $checkoutCart */
        $checkoutCart = self::getContainer()->get(CheckoutCart::class);

        $owner = UserAssembler::new()->assemble();
        $intruder = UserAssembler::new()->assemble();
        $em->persist($owner);
        $em->persist($intruder);
        $em->flush();
        $intruderId = $intruder->getId();
        static::assertNotNull($intruderId);

        $cart = new Cart(id: new Ulid(), customerId: $owner->getId() ?? 0, currency: 'PLN');
        $em->persist($cart);
        $em->flush();

        $this->expectException(\InvalidArgumentException::class);
        $checkoutCart($cart->id, $intruderId);
    }

    public function testTwoCustomersCannotTakeTheLastSeat(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $add = self::getContainer()->get(AddCartItem::class);
        $checkout = self::getContainer()->get(CheckoutCart::class);
        $firstUser = UserAssembler::new()->assemble();
        $secondUser = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->withCapacity(1)->assemble();
        $em->persist($firstUser);
        $em->persist($secondUser);
        $em->persist($lesson);
        $em->flush();

        $firstCart = new Cart(new Ulid(), $firstUser->getId() ?? 0, 'PLN');
        $secondCart = new Cart(new Ulid(), $secondUser->getId() ?? 0, 'PLN');
        $em->persist($firstCart);
        $em->persist($secondCart);
        $em->flush();
        $add($firstCart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $firstUser->getId() ?? 0);
        $add($secondCart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $secondUser->getId() ?? 0);

        $checkout($firstCart->id, $firstUser->getId() ?? 0, 'SEAT');

        $this->expectException(CapacityExceededException::class);
        $checkout($secondCart->id, $secondUser->getId() ?? 0, 'FULL');
    }

    public function testMultiLineCapacityFailureCreatesNothing(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $add = self::getContainer()->get(AddCartItem::class);
        $checkout = self::getContainer()->get(CheckoutCart::class);
        $customer = UserAssembler::new()->assemble();
        $occupant = UserAssembler::new()->assemble();
        $available = LessonAssembler::new()->withCapacity(2)->assemble();
        $full = LessonAssembler::new()->withCapacity(1)->assemble();
        $existing = BookingAssembler::new()
            ->withUser($occupant)
            ->withLessons($full)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $em->persist($customer);
        $em->persist($occupant);
        $em->persist($available);
        $em->persist($full);
        $em->persist($existing);
        $em->flush();

        $cart = new Cart(new Ulid(), $customer->getId() ?? 0, 'PLN');
        $em->persist($cart);
        $em->flush();
        $add($cart->id, (string) $available->getId(), TicketType::ONE_TIME->value, null, $customer->getId() ?? 0);
        $add($cart->id, (string) $full->getId(), TicketType::ONE_TIME->value, null, $customer->getId() ?? 0);

        try {
            $checkout($cart->id, $customer->getId() ?? 0, 'NONE');
            static::fail('Expected capacity rejection.');
        } catch (CapacityExceededException) {
            static::assertCount(0, $em->getRepository(CustomerOrder::class)->findAll());
            static::assertCount(1, $em->getRepository(Booking::class)->findAll());
            static::assertSame(Cart::STATUS_OPEN, $cart->status);
        }
    }

    public function testTwoCustomersCannotConsumeTheLastPromotionUse(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $add = self::getContainer()->get(AddCartItem::class);
        $apply = self::getContainer()->get(ApplyPromotionCode::class);
        $checkout = self::getContainer()->get(CheckoutCart::class);
        $rule = new PricingRule(
            new Ulid(),
            'Once',
            AdjustmentType::FIXED_AMOUNT_OFF,
            1000,
            promotionCode: 'ONCE',
            usageLimit: 1,
        );
        $lesson = LessonAssembler::new()->assemble();
        $first = UserAssembler::new()->assemble();
        $second = UserAssembler::new()->assemble();
        $em->persist($rule);
        $em->persist($lesson);
        $em->persist($first);
        $em->persist($second);
        $em->flush();

        foreach ([$first, $second] as $index => $user) {
            $cart = new Cart(new Ulid(), $user->getId() ?? 0, 'PLN');
            $em->persist($cart);
            $em->flush();
            $add($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $user->getId() ?? 0);
            $apply($cart->id, 'ONCE', $user->getId() ?? 0);
            if ($index === 0) {
                $checkout($cart->id, $user->getId() ?? 0, 'PROM');
                continue;
            }

            try {
                $checkout($cart->id, $user->getId() ?? 0, 'LIMT');
                static::fail('Expected promotion limit rejection.');
            } catch (PromotionLimitExceededException) {
                static::assertCount(1, $em->getRepository(PromotionRedemption::class)->findBy([
                    'status' => PromotionRedemption::STATUS_RESERVED,
                ]));
            }
        }
    }
}
