<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Cart;

use App\Application\UseCase\Cart\AddCartItem;
use App\Application\UseCase\Cart\ApplyPromotionCode;
use App\Application\UseCase\Cart\InvalidPromotionCodeException;
use App\Application\UseCase\Cart\RemovePromotionCode;
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
final class PromotionCodeTest extends KernelTestCase
{
    use CartScenario;

    public function testApplyingAValidCodeNormalizesItAndRepricesTheItems(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ApplyPromotionCode $applyPromotionCode */
        $applyPromotionCode = self::getContainer()->get(ApplyPromotionCode::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->persist(new PricingRule(
            id: new Ulid(),
            name: 'Cart promo',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 1_000,
            promotionCode: 'CARTPROMO',
        ));
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();
        $item = $addCartItem($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);
        static::assertSame(5000, $item->finalPriceMinor);

        $updated = $applyPromotionCode($cart->id, ' cartpromo ', $userId);

        static::assertSame('CARTPROMO', $updated->promotionCode);
        static::assertSame(4000, $item->finalPriceMinor);
    }

    public function testApplyingAnUnknownCodeThrowsWithoutMutatingTheCart(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var ApplyPromotionCode $applyPromotionCode */
        $applyPromotionCode = self::getContainer()->get(ApplyPromotionCode::class);

        $user = UserAssembler::new()->assemble();
        $em->persist($user);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();

        $this->expectException(InvalidPromotionCodeException::class);
        $applyPromotionCode($cart->id, 'DOESNOTEXIST', $userId);
    }

    public function testRemovingThePromotionCodeRepricesBackToTheBasePrice(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ApplyPromotionCode $applyPromotionCode */
        $applyPromotionCode = self::getContainer()->get(ApplyPromotionCode::class);
        /** @var RemovePromotionCode $removePromotionCode */
        $removePromotionCode = self::getContainer()->get(RemovePromotionCode::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->persist(new PricingRule(
            id: new Ulid(),
            name: 'Cart promo',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 1_000,
            promotionCode: 'CARTPROMO2',
        ));
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();
        $item = $addCartItem($cart->id, (string) $lesson->getId(), TicketType::ONE_TIME->value, null, $userId);
        $applyPromotionCode($cart->id, 'CARTPROMO2', $userId);
        static::assertSame(4000, $item->finalPriceMinor);

        $updated = $removePromotionCode($cart->id, $userId);

        static::assertNull($updated->promotionCode);
        static::assertSame(5000, $item->finalPriceMinor);
    }

    public function testApplyingACodeToAnotherCustomersCartIsRejected(): void
    {
        $em = $this->entityManager();
        /** @var ApplyPromotionCode $applyPromotionCode */
        $applyPromotionCode = self::getContainer()->get(ApplyPromotionCode::class);
        $owner = $this->persistCustomer($em);
        $intruder = $this->persistCustomer($em);
        $em->persist(new PricingRule(
            id: new Ulid(),
            name: 'Foreign cart promo',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 1_000,
            promotionCode: 'FOREIGNCART',
        ));
        $cart = $this->persistCart($em, $owner);

        try {
            $applyPromotionCode($cart->id, 'FOREIGNCART', $this->idOf($intruder));
            static::fail("Expected another customer's cart to be rejected.");
        } catch (\InvalidArgumentException $exception) {
            static::assertStringContainsString('not found for this customer', $exception->getMessage());
        }

        static::assertNull($cart->promotionCode);
    }

    public function testApplyingACodeToAConvertedCartIsRejected(): void
    {
        $em = $this->entityManager();
        /** @var ApplyPromotionCode $applyPromotionCode */
        $applyPromotionCode = self::getContainer()->get(ApplyPromotionCode::class);
        $user = $this->persistCustomer($em);
        $em->persist(new PricingRule(
            id: new Ulid(),
            name: 'Converted cart promo',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 1_000,
            promotionCode: 'CONVERTEDCART',
        ));
        $cart = $this->persistCart($em, $user, Cart::STATUS_CONVERTED);

        try {
            $applyPromotionCode($cart->id, 'CONVERTEDCART', $this->idOf($user));
            static::fail('Expected a converted cart to be immutable.');
        } catch (\LogicException $exception) {
            static::assertStringContainsString('not open', $exception->getMessage());
        }

        static::assertNull($cart->promotionCode);
    }

    public function testAnUnknownCodeLeavesAnAlreadyAppliedCodeAndPricesInPlace(): void
    {
        $em = $this->entityManager();
        /** @var AddCartItem $addCartItem */
        $addCartItem = self::getContainer()->get(AddCartItem::class);
        /** @var ApplyPromotionCode $applyPromotionCode */
        $applyPromotionCode = self::getContainer()->get(ApplyPromotionCode::class);
        $user = $this->persistCustomer($em);
        $lesson = $this->persistLesson($em);
        $em->persist(new PricingRule(
            id: new Ulid(),
            name: 'Keep promo',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 1_000,
            promotionCode: 'KEEPME',
        ));
        $cart = $this->persistCart($em, $user);
        $item = $addCartItem(
            $cart->id,
            (string) $lesson->getId(),
            TicketType::ONE_TIME->value,
            null,
            $this->idOf($user),
        );
        $applyPromotionCode($cart->id, 'KEEPME', $this->idOf($user));
        static::assertSame(4000, $item->finalPriceMinor);

        $rejected = false;
        try {
            $applyPromotionCode($cart->id, 'TYPO', $this->idOf($user));
        } catch (InvalidPromotionCodeException) {
            $rejected = true;
        }

        static::assertTrue($rejected, 'Expected an unknown code to be rejected.');

        static::assertSame('KEEPME', $cart->promotionCode);
        static::assertSame(4000, $item->finalPriceMinor);
    }
}
