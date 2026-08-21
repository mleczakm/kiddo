<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\UseCase\Cart\AddCartItem;
use App\Application\UseCase\Cart\RemoveCartItem;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Cart\CartItem;
use App\Entity\TicketType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class RemoveCartItemTest extends KernelTestCase
{
    public function testRemovingAnItemDeletesIt(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var RemoveCartItem $removeCartItem */
        $removeCartItem = self::getContainer()->get(RemoveCartItem::class);

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

        $item = $addCartItem($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);

        $removeCartItem($item->id, $userId);

        static::assertCount(0, $em->getRepository(CartItem::class)->findAll());
    }

    public function testCannotRemoveAnotherCustomersItem(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var RemoveCartItem $removeCartItem */
        $removeCartItem = self::getContainer()->get(RemoveCartItem::class);

        $owner = UserAssembler::new()->assemble();
        $intruder = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($owner);
        $em->persist($intruder);
        $em->persist($lesson);
        $em->flush();
        $ownerId = $owner->getId();
        $intruderId = $intruder->getId();
        static::assertNotNull($ownerId);
        static::assertNotNull($intruderId);

        $cart = new Cart(id: new Ulid(), customerId: $ownerId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();
        $item = $addCartItem($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $ownerId);

        $this->expectException(\InvalidArgumentException::class);
        $removeCartItem($item->id, $intruderId);
    }
}
