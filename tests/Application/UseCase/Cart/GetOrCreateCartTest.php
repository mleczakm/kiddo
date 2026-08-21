<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\UseCase\Cart\GetOrCreateCart;
use App\Domain\Commerce\Cart\Cart;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class GetOrCreateCartTest extends KernelTestCase
{
    public function testCreatesAnOpenCartWhenNoneExists(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var GetOrCreateCart $useCase */
        $useCase = self::getContainer()->get(GetOrCreateCart::class);

        $user = UserAssembler::new()->assemble();
        $em->persist($user);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = $useCase($userId, 'PLN');

        static::assertSame(Cart::STATUS_OPEN, $cart->status);
        static::assertSame($userId, $cart->customerId);
        static::assertSame('PLN', $cart->currency);
        static::assertCount(1, $em->getRepository(Cart::class)->findAll());
    }

    public function testReturnsTheSameOpenCartOnASecondCall(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var GetOrCreateCart $useCase */
        $useCase = self::getContainer()->get(GetOrCreateCart::class);

        $user = UserAssembler::new()->assemble();
        $em->persist($user);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $first = $useCase($userId, 'PLN');
        $second = $useCase($userId, 'PLN');

        static::assertTrue($first->id->equals($second->id));
        static::assertCount(1, $em->getRepository(Cart::class)->findAll());
    }

    public function testDifferentCurrenciesGetSeparateCarts(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var GetOrCreateCart $useCase */
        $useCase = self::getContainer()->get(GetOrCreateCart::class);

        $user = UserAssembler::new()->assemble();
        $em->persist($user);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $pln = $useCase($userId, 'PLN');
        $eur = $useCase($userId, 'EUR');

        static::assertFalse($pln->id->equals($eur->id));
    }
}
