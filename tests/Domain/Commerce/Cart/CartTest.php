<?php

declare(strict_types=1);

namespace App\Tests\Domain\Commerce\Cart;

use App\Domain\Commerce\Cart\Cart;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

#[Group('unit')]
final class CartTest extends TestCase
{
    public function testNewCartIsOpen(): void
    {
        $cart = new Cart(id: new Ulid(), customerId: 1, currency: 'PLN');

        static::assertTrue($cart->isOpen());
        static::assertSame(Cart::STATUS_OPEN, $cart->status);
    }

    public function testConvertMarksTheCartConvertedAndRecordsTheOrderId(): void
    {
        $cart = new Cart(id: new Ulid(), customerId: 1, currency: 'PLN');
        $orderId = new Ulid();

        $cart->convert($orderId);

        static::assertFalse($cart->isOpen());
        static::assertSame(Cart::STATUS_CONVERTED, $cart->status);
        static::assertTrue($cart->convertedOrderId?->equals($orderId));
    }

    public function testConvertingAnAlreadyConvertedCartThrows(): void
    {
        $cart = new Cart(id: new Ulid(), customerId: 1, currency: 'PLN');
        $cart->convert(new Ulid());

        $this->expectException(\LogicException::class);
        $cart->convert(new Ulid());
    }

    public function testAssertOpenThrowsOnceConverted(): void
    {
        $cart = new Cart(id: new Ulid(), customerId: 1, currency: 'PLN');
        $cart->convert(new Ulid());

        $this->expectException(\LogicException::class);
        $cart->assertOpen();
    }

    public function testTouchAdvancesUpdatedAt(): void
    {
        $cart = new Cart(
            id: new Ulid(),
            customerId: 1,
            currency: 'PLN',
            updatedAt: new \DateTimeImmutable('2020-01-01'),
        );

        $cart->touch();

        static::assertGreaterThan(new \DateTimeImmutable('2020-01-01'), $cart->updatedAt);
    }
}
