<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Domain\Commerce\Cart\Cart;
use App\Entity\Child;
use App\Entity\Lesson;
use App\Entity\User;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Shared arrangement for the cart use case tests that exercise rejection
 * paths: a persisted customer with a cart, a lesson, and optionally a
 * participant, without repeating the boot/persist/flush boilerplate.
 *
 * @phpstan-require-extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
 */
trait CartScenario
{
    private function entityManager(): EntityManagerInterface
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('The test container must expose the entity manager.');
        }

        return $em;
    }

    private function persistCustomer(EntityManagerInterface $em): User
    {
        $user = UserAssembler::new()->assemble();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function persistLesson(EntityManagerInterface $em): Lesson
    {
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);
        $em->flush();

        return $lesson;
    }

    private function persistChild(EntityManagerInterface $em, User $owner): Child
    {
        $child = new Child($owner, 'Zosia', new \DateTimeImmutable('2020-01-01'));
        $em->persist($child);
        $em->flush();

        return $child;
    }

    private function persistCart(EntityManagerInterface $em, User $customer, string $status = Cart::STATUS_OPEN): Cart
    {
        $cart = new Cart(id: new Ulid(), customerId: $this->idOf($customer), currency: 'PLN', status: $status);
        $em->persist($cart);
        $em->flush();

        return $cart;
    }

    private function idOf(User $user): int
    {
        $id = $user->getId();
        static::assertNotNull($id);

        return $id;
    }
}
