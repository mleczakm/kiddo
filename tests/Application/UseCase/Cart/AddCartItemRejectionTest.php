<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\UseCase\Cart\AddCartItem;
use App\Domain\Commerce\Cart\CartItem;
use App\Entity\TicketType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class AddCartItemRejectionTest extends KernelTestCase
{
    use CartScenario;

    public function testAddingToACartThatDoesNotExistIsRejected(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);
        $user = $this->persistCustomer($em);
        $lesson = $this->persistLesson($em);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found for this customer');
        $useCase(new Ulid(), (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $this->idOf($user));
    }

    public function testAddingALessonThatDoesNotExistIsRejectedWithoutTouchingTheCart(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);
        $user = $this->persistCustomer($em);
        $cart = $this->persistCart($em, $user);
        $missingLesson = new Ulid();

        try {
            $useCase($cart->id, (string) $missingLesson, TicketType::ONE_TIME->value, null, $this->idOf($user));
            static::fail('Expected an unknown lesson to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            static::assertStringContainsString('Lesson', $exception->getMessage());
        }

        static::assertCount(0, $em->getRepository(CartItem::class)->findAll());
    }

    public function testAnUnsupportedTicketTypeIsRejected(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);
        $user = $this->persistCustomer($em);
        $lesson = $this->persistLesson($em);
        $cart = $this->persistCart($em, $user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported ticket type');
        $useCase($cart->id, (string) $lesson->getId(), 'no-such-ticket', null, $this->idOf($user));
    }

    public function testAParticipantThatDoesNotExistIsRejected(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);
        $user = $this->persistCustomer($em);
        $lesson = $this->persistLesson($em);
        $cart = $this->persistCart($em, $user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Participant');
        $useCase(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            (string) new Ulid(),
            $this->idOf($user),
        );
    }

    public function testAnotherCustomersChildCannotBeAddedAsParticipant(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $useCase */
        $useCase = self::getContainer()->get(AddCartItem::class);
        $user = $this->persistCustomer($em);
        $stranger = $this->persistCustomer($em);
        $strangersChild = $this->persistChild($em, $stranger);
        $lesson = $this->persistLesson($em);
        $cart = $this->persistCart($em, $user);

        try {
            $useCase(
                $cart->id,
                (string) $lesson->getId(),
                TicketType::ONE_TIME->value,
                (string) $strangersChild->getId(),
                $this->idOf($user),
            );
            static::fail('Expected a participant owned by someone else to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            static::assertStringContainsString('not found for this customer', $exception->getMessage());
        }

        static::assertCount(0, $em->getRepository(CartItem::class)->findAll());
    }
}
