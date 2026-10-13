<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Domain\Commerce\Order\OrderLine;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Domain\Commerce\Pricing\PromotionRedemption;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class PromotionRedemptionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PromotionRedemptionBatch $batch,
    ) {}

    /** @param list<OrderLine> $lines */
    public function reserve(Ulid $orderId, int $customerId, array $lines): void
    {
        $rules = $this->batch->lockRules($lines);
        if ($rules === []) {
            return;
        }
        $ids = array_values(array_map(static fn(PricingRule $rule): string => $rule->id->toRfc4122(), $rules));
        $usage = $this->batch->activeUsage($ids, $customerId);

        foreach ($lines as $line) {
            $snapshot = $line->getPricingSnapshotJson();
            foreach ($snapshot['adjustments'] ?? [] as $adjustment) {
                $ruleId = Ulid::fromString($adjustment['ruleId']);
                $key = (string) $ruleId;
                $rule = $rules[$key] ?? null;
                if (!$rule instanceof PricingRule || $rule->usageLimit === null && $rule->perUserLimit === null) {
                    continue;
                }

                $counts = $usage[$key] ?? ['total' => 0, 'customer' => 0];
                if (
                    $rule->usageLimit !== null && $counts['total'] >= $rule->usageLimit
                    || $rule->perUserLimit !== null && $counts['customer'] >= $rule->perUserLimit
                ) {
                    throw new PromotionLimitExceededException(sprintf('Pricing rule %s has no uses left.', $ruleId));
                }

                $this->em->persist(new PromotionRedemption(new Ulid(), $ruleId, $orderId, $line->getId(), $customerId));
                $usage[$key] = ['total' => $counts['total'] + 1, 'customer' => $counts['customer'] + 1];
            }
        }
        $this->em->flush();
    }

    public function consumeForOrder(Ulid $orderId): void
    {
        foreach ($this->findForOrder($orderId) as $redemption) {
            $redemption->consume();
        }
    }

    public function releaseForOrder(Ulid $orderId): void
    {
        foreach ($this->findForOrder($orderId) as $redemption) {
            $redemption->release();
        }
    }

    /** @return list<PromotionRedemption> */
    private function findForOrder(Ulid $orderId): array
    {
        return $this->em->getRepository(PromotionRedemption::class)->findBy(['orderId' => $orderId]);
    }
}
