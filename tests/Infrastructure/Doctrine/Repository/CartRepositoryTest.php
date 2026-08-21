<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Repository;

use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Cart\CartItem;
use App\Infrastructure\Doctrine\Repository\CartItemRepository;
use App\Infrastructure\Doctrine\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class CartRepositoryTest extends KernelTestCase
{
    public function testFindOpenForCustomerOnlyReturnsTheOpenCartForThatCustomerAndCurrency(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var CartRepository $cartRepository */
        $cartRepository = self::getContainer()->get(CartRepository::class);

        $open = new Cart(id: new Ulid(), customerId: 501, currency: 'PLN');
        $converted = new Cart(id: new Ulid(), customerId: 501, currency: 'PLN', status: Cart::STATUS_CONVERTED);
        $otherCurrency = new Cart(id: new Ulid(), customerId: 501, currency: 'EUR');
        $otherCustomer = new Cart(id: new Ulid(), customerId: 502, currency: 'PLN');
        $em->persist($open);
        $em->persist($converted);
        $em->persist($otherCurrency);
        $em->persist($otherCustomer);
        $em->flush();
        $em->clear();

        $found = $cartRepository->findOpenForCustomer(501, 'PLN');

        static::assertNotNull($found);
        static::assertTrue($found->id->equals($open->id));
    }

    public function testFindOpenForCustomerReturnsNullWhenThereIsNoOpenCart(): void
    {
        self::bootKernel();
        /** @var CartRepository $cartRepository */
        $cartRepository = self::getContainer()->get(CartRepository::class);

        static::assertNull($cartRepository->findOpenForCustomer(999_999, 'PLN'));
    }

    public function testCartItemRoundTripsAndFindByCartOrdersByAddedAt(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var CartItemRepository $cartItemRepository */
        $cartItemRepository = self::getContainer()->get(CartItemRepository::class);

        $cart = new Cart(id: new Ulid(), customerId: 601, currency: 'PLN');
        $em->persist($cart);

        $first = new CartItem(
            id: new Ulid(),
            cartId: $cart->id,
            lessonId: new Ulid(),
            ticketType: 'one_time',
            participantId: null,
            basePriceMinor: 5000,
            finalPriceMinor: 4500,
            currency: 'PLN',
            pricingQuoteHash: 'hash-1',
            quotedAt: new \DateTimeImmutable('2026-08-21 10:00:00'),
            addedAt: new \DateTimeImmutable('2026-08-21 10:00:00'),
        );
        $second = new CartItem(
            id: new Ulid(),
            cartId: $cart->id,
            lessonId: new Ulid(),
            ticketType: 'carnet_4',
            participantId: new Ulid(),
            basePriceMinor: 18_000,
            finalPriceMinor: 18_000,
            currency: 'PLN',
            pricingQuoteHash: null,
            quotedAt: null,
            addedAt: new \DateTimeImmutable('2026-08-21 10:05:00'),
        );
        $em->persist($first);
        $em->persist($second);
        $em->flush();
        $em->clear();

        $items = $cartItemRepository->findByCart($cart->id);

        static::assertCount(2, $items);
        static::assertTrue($items[0]->id->equals($first->id));
        static::assertTrue($items[1]->id->equals($second->id));
        static::assertSame(4500, $items[0]->finalPriceMinor);
        static::assertSame('hash-1', $items[0]->pricingQuoteHash);
        static::assertNull($items[1]->pricingQuoteHash);
        static::assertNotNull($items[1]->participantId);
    }
}
