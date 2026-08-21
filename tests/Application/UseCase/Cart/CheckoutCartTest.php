<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\UseCase\Cart\AddCartItem;
use App\Application\UseCase\Cart\CheckoutCart;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use App\Entity\TicketType;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
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
}
