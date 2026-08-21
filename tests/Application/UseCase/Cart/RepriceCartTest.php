<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\UseCase\Cart\AddCartItem;
use App\Application\UseCase\Cart\RepriceCart;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Entity\TicketType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class RepriceCartTest extends KernelTestCase
{
    public function testRepriceReflectsAPricingRuleAddedAfterTheItemWasAdded(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var RepriceCart $repriceCart */
        $repriceCart = self::getContainer()->get(RepriceCart::class);

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
        static::assertSame(5000, $item->finalPriceMinor);

        // A rule created after the item was added - reprice should pick it up.
        $em->persist(new PricingRule(
            id: new Ulid(),
            name: 'Late rule',
            adjustmentType: AdjustmentType::PERCENTAGE_OFF,
            adjustmentValue: 10,
        ));
        $em->flush();

        $repriceCart($cart->id, $userId);

        static::assertSame(4500, $item->finalPriceMinor);
    }

    public function testRepricingSomeoneElsesCartThrows(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var RepriceCart $repriceCart */
        $repriceCart = self::getContainer()->get(RepriceCart::class);

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
        $repriceCart($cart->id, $intruderId);
    }
}
