<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\UseCase\Cart\AddCartItem;
use App\Application\UseCase\Cart\DuplicateCartItemException;
use App\Domain\Commerce\Cart\Cart;
use App\Entity\Child;
use App\Entity\TicketType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class AddCartItemTest extends KernelTestCase
{
    public function testAddingAnItemPricesItImmediately(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);

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

        $item = $useCase($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);

        static::assertSame(5000, $item->finalPriceMinor);
        static::assertNotNull($item->quotedAt);
        static::assertNotNull($item->pricingQuoteHash);
    }

    public function testAddingTheSameSelectionTwiceIsRejectedExplicitly(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);

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

        $useCase($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);

        $this->expectException(DuplicateCartItemException::class);
        $useCase($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);
    }

    public function testTheSameLessonWithADifferentParticipantIsNotADuplicate(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $child = new Child($user, 'Zosia', new \DateTimeImmutable('2020-01-01'));
        $em->persist($user);
        $em->persist($lesson);
        $em->persist($child);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();

        $useCase($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);
        $second = $useCase(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            (string) $child->getId(),
            $userId,
        );

        static::assertTrue($second->participantId?->equals($child->getId()));
    }

    public function testCannotAddToAnotherCustomersCart(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);

        $owner = UserAssembler::new()->assemble();
        $intruder = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($owner);
        $em->persist($intruder);
        $em->persist($lesson);
        $em->flush();
        $intruderId = $intruder->getId();
        static::assertNotNull($intruderId);

        $cart = new Cart(id: new Ulid(), customerId: $owner->getId() ?? 0, currency: 'PLN');
        $em->persist($cart);
        $em->flush();

        $this->expectException(\InvalidArgumentException::class);
        $useCase($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $intruderId);
    }

    public function testCannotAddToAConvertedCart(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN', status: Cart::STATUS_CONVERTED);
        $em->persist($cart);
        $em->flush();

        $this->expectException(\LogicException::class);
        $useCase($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);
    }
}
