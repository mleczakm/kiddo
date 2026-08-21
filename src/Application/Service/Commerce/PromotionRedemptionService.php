<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Domain\Commerce\Order\OrderLine;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Domain\Commerce\Pricing\PromotionRedemption;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class PromotionRedemptionService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /** @param list<OrderLine> $lines */
    public function reserve(Ulid $orderId, int $customerId, array $lines): void
    {
        foreach ($lines as $line) {
            $snapshot = $line->getPricingSnapshotJson();
            foreach ($snapshot['adjustments'] ?? [] as $adjustment) {
                $ruleId = Ulid::fromString($adjustment['ruleId']);
                $rule = $this->em->find(PricingRule::class, $ruleId, LockMode::PESSIMISTIC_WRITE);
                if (!$rule instanceof PricingRule || $rule->usageLimit === null && $rule->perUserLimit === null) {
                    continue;
                }

                $global = $this->countActive($ruleId);
                $forUser = $this->countActive($ruleId, $customerId);
                if (
                    $rule->usageLimit !== null && $global >= $rule->usageLimit
                    || $rule->perUserLimit !== null && $forUser >= $rule->perUserLimit
                ) {
                    throw new PromotionLimitExceededException(sprintf('Pricing rule %s has no uses left.', $ruleId));
                }

                $this->em->persist(new PromotionRedemption(new Ulid(), $ruleId, $orderId, $line->getId(), $customerId));
                $this->em->flush();
            }
        }
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

    private function countActive(Ulid $ruleId, ?int $customerId = null): int
    {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(PromotionRedemption::class, 'r')
            ->where('r.pricingRuleId = :rule')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('rule', $ruleId, 'ulid')
            ->setParameter('statuses', [PromotionRedemption::STATUS_RESERVED, PromotionRedemption::STATUS_CONSUMED]);
        if ($customerId !== null) {
            $qb->andWhere('r.customerId = :customer')->setParameter('customer', $customerId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return list<PromotionRedemption> */
    private function findForOrder(Ulid $orderId): array
    {
        return $this->em->getRepository(PromotionRedemption::class)->findBy(['orderId' => $orderId]);
    }
}
