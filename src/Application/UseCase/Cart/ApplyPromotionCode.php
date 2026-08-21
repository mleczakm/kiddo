<?php

declare(strict_types=1);

namespace App\Application\UseCase\Cart;

use App\Application\Repository\CartRepositoryInterface;
use App\Application\Repository\PricingRuleRepositoryInterface;
use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Pricing\PromotionCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class ApplyPromotionCode
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private PricingRuleRepositoryInterface $pricingRuleRepository,
        private RepriceCart $repriceCart,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Ulid $cartId, string $code, int $requestingUserId): Cart
    {
        $cart = $this->cartRepository->find($cartId);
        if ($cart === null || $cart->customerId !== $requestingUserId) {
            throw new \InvalidArgumentException(sprintf('Cart %s not found for this customer.', $cartId));
        }
        $cart->assertOpen();

        $normalized = PromotionCode::normalize($code);
        $matchingRules = array_filter(
            $this->pricingRuleRepository->findActive(),
            static fn($rule): bool => (
                $rule->promotionCode !== null
                && strcasecmp($rule->promotionCode, $normalized) === 0
            ),
        );
        if ($matchingRules === []) {
            throw new InvalidPromotionCodeException(sprintf('"%s" is not a valid promotion code.', $code));
        }

        $cart->promotionCode = $normalized;
        $this->repriceCart->reprice($cart);
        $this->em->flush();

        return $cart;
    }
}
