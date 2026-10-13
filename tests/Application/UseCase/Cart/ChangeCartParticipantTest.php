<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\UseCase\Cart\AddCartItem;
use App\Application\UseCase\Cart\ChangeCartParticipant;
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
final class ChangeCartParticipantTest extends KernelTestCase
{
    use CartScenario;

    public function testChangesTheParticipantOnTheItem(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ChangeCartParticipant $changeParticipant */
        $changeParticipant = self::getContainer()->get(ChangeCartParticipant::class);

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
        $item = $addCartItem($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);

        $updated = $changeParticipant($item->id, (string) $child->getId(), $userId);

        static::assertTrue($updated->participantId?->equals($child->getId()));
    }

    public function testClearingTheParticipantSetsItToNull(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ChangeCartParticipant $changeParticipant */
        $changeParticipant = self::getContainer()->get(ChangeCartParticipant::class);

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
        $item = $addCartItem(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            (string) $child->getId(),
            $userId,
        );

        $updated = $changeParticipant($item->id, null, $userId);

        static::assertNull($updated->participantId);
    }

    public function testChangingToAParticipantThatWouldDuplicateAnotherItemThrows(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ChangeCartParticipant $changeParticipant */
        $changeParticipant = self::getContainer()->get(ChangeCartParticipant::class);

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

        // One item for the customer themself, one for the child.
        $selfItem = $addCartItem($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);
        $addCartItem(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            (string) $child->getId(),
            $userId,
        );

        $this->expectException(DuplicateCartItemException::class);
        $changeParticipant($selfItem->id, (string) $child->getId(), $userId);
    }

    public function testChangingAnItemThatDoesNotExistIsRejected(): void
    {
        $em = $this->entityManager();
        /** @var ChangeCartParticipant $changeParticipant */
        $changeParticipant = self::getContainer()->get(ChangeCartParticipant::class);
        $user = $this->persistCustomer($em);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');
        $changeParticipant(new Ulid(), null, $this->idOf($user));
    }

    public function testCannotChangeAnotherCustomersItem(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ChangeCartParticipant $changeParticipant */
        $changeParticipant = self::getContainer()->get(ChangeCartParticipant::class);
        $owner = $this->persistCustomer($em);
        $intruder = $this->persistCustomer($em);
        $child = $this->persistChild($em, $intruder);
        $lesson = $this->persistLesson($em);
        $cart = $this->persistCart($em, $owner);
        $item = $addCartItem(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            null,
            $this->idOf($owner),
        );

        try {
            $changeParticipant($item->id, (string) $child->getId(), $this->idOf($intruder));
            static::fail("Expected another customer's item to be rejected.");
        } catch (\InvalidArgumentException $exception) {
            static::assertStringContainsString('not found for this customer', $exception->getMessage());
        }

        static::assertNull($item->participantId);
    }

    public function testAParticipantThatDoesNotExistIsRejected(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ChangeCartParticipant $changeParticipant */
        $changeParticipant = self::getContainer()->get(ChangeCartParticipant::class);
        $user = $this->persistCustomer($em);
        $lesson = $this->persistLesson($em);
        $cart = $this->persistCart($em, $user);
        $item = $addCartItem(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            null,
            $this->idOf($user),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Participant');
        $changeParticipant($item->id, (string) new Ulid(), $this->idOf($user));
    }

    public function testAnotherCustomersChildCannotBecomeTheParticipant(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ChangeCartParticipant $changeParticipant */
        $changeParticipant = self::getContainer()->get(ChangeCartParticipant::class);
        $user = $this->persistCustomer($em);
        $stranger = $this->persistCustomer($em);
        $strangersChild = $this->persistChild($em, $stranger);
        $lesson = $this->persistLesson($em);
        $cart = $this->persistCart($em, $user);
        $item = $addCartItem(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            null,
            $this->idOf($user),
        );

        try {
            $changeParticipant($item->id, (string) $strangersChild->getId(), $this->idOf($user));
            static::fail('Expected a participant owned by someone else to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            static::assertStringContainsString('not found for this customer', $exception->getMessage());
        }

        static::assertNull($item->participantId);
    }

    public function testCannotChangeTheParticipantOnAConvertedCart(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ChangeCartParticipant $changeParticipant */
        $changeParticipant = self::getContainer()->get(ChangeCartParticipant::class);
        $user = $this->persistCustomer($em);
        $child = $this->persistChild($em, $user);
        $lesson = $this->persistLesson($em);
        $cart = $this->persistCart($em, $user);
        $item = $addCartItem(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            null,
            $this->idOf($user),
        );
        $cart->convert(new Ulid());
        $em->flush();

        try {
            $changeParticipant($item->id, (string) $child->getId(), $this->idOf($user));
            static::fail('Expected a converted cart to be immutable.');
        } catch (\LogicException $exception) {
            static::assertStringContainsString('not open', $exception->getMessage());
        }

        static::assertNull($item->participantId);
    }
}
