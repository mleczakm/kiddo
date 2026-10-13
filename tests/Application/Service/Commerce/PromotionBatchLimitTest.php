<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Commerce;

use App\Application\Service\Commerce\PromotionLimitExceededException;
use App\Application\UseCase\Cart\AddCartItem;
use App\Application\UseCase\Cart\ApplyPromotionCode;
use App\Application\UseCase\Cart\CheckoutCart;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class PromotionBatchLimitTest extends KernelTestCase
{
    /** @return iterable<string, array{?int, ?int}> */
    public static function limits(): iterable
    {
        yield 'global limit' => [1, null];
        yield 'customer limit' => [null, 1];
    }

    #[DataProvider('limits')]
    public function testSeveralLinesCannotOverrunOneRemainingUse(?int $global, ?int $customer): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var AddCartItem $add */
        $add = self::getContainer()->get(AddCartItem::class);
        /** @var ApplyPromotionCode $apply */
        $apply = self::getContainer()->get(ApplyPromotionCode::class);
        /** @var CheckoutCart $checkout */
        $checkout = self::getContainer()->get(CheckoutCart::class);
        $user = UserAssembler::new()->assemble();
        $rule = new PricingRule(
            new Ulid(),
            'Limited',
            AdjustmentType::FIXED_AMOUNT_OFF,
            1000,
            promotionCode: 'LIMIT',
            usageLimit: $global,
            perUserLimit: $customer,
        );
        $lessons = [LessonAssembler::new()->assemble(), LessonAssembler::new()->assemble()];
        $em->persist($user);
        $em->persist($rule);
        foreach ($lessons as $lesson) {
            $em->persist($lesson);
        }
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);
        $cart = new Cart(new Ulid(), $userId, 'PLN');
        $em->persist($cart);
        $em->flush();
        foreach ($lessons as $lesson) {
            $add($cart->id, (string) $lesson->getId(), 'one_time', null, $userId);
        }
        $apply($cart->id, 'LIMIT', $userId);

        $this->expectException(PromotionLimitExceededException::class);
        $checkout($cart->id, $userId, 'BATCH');
    }
}
