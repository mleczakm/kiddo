<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Domain\Commerce\Order\OrderLine;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Domain\Commerce\Pricing\PromotionRedemption;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Ulid;

final readonly class PromotionRedemptionBatch
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /** @param list<OrderLine> $lines
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @return array<string, PricingRule>
     */
    public function lockRules(array $lines): array
    {
        $ids = [];
        foreach ($lines as $line) {
            foreach ($line->getPricingSnapshotJson()['adjustments'] ?? [] as $adjustment) {
                $id = Ulid::fromString($adjustment['ruleId']);
                $ids[(string) $id] = $id->toRfc4122();
            }
        }
        if ($ids === []) {
            return [];
        }

        // Lock all rules in a stable order before reading their usage. A refresh is
        // required because quoting may already have loaded a rule before the lock.
        /** @var list<PricingRule> $lockedRules */
        $lockedRules = $this->em
            ->createQueryBuilder()
            ->select('rule')
            ->from(PricingRule::class, 'rule')
            ->where('rule.id IN (:ids)')
            ->setParameter('ids', array_values($ids))
            ->orderBy('rule.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
        $rules = [];
        foreach ($lockedRules as $rule) {
            $rules[(string) $rule->id] = $rule;
        }
        return $rules;
    }

    /**
     * @param list<string> $ids
     * @return array<string, array{total: int, customer: int}>
     */
    public function activeUsage(array $ids, int $customerId): array
    {
        /** @var list<array{ruleId: Ulid, total: numeric-string, customerUses: numeric-string}> $rows */
        $rows = $this->em
            ->createQueryBuilder()
            ->select('r.pricingRuleId AS ruleId, COUNT(r.id) AS total')
            ->addSelect('SUM(CASE WHEN r.customerId = :customer THEN 1 ELSE 0 END) AS customerUses')
            ->from(PromotionRedemption::class, 'r')
            ->where('r.pricingRuleId IN (:rules)')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('rules', $ids)
            ->setParameter('customer', $customerId)
            ->setParameter('statuses', [PromotionRedemption::STATUS_RESERVED, PromotionRedemption::STATUS_CONSUMED])
            ->groupBy('r.pricingRuleId')
            ->getQuery()
            ->getArrayResult();
        $usage = [];
        foreach ($rows as $row) {
            $usage[(string) $row['ruleId']] = [
                'total' => (int) $row['total'],
                'customer' => (int) $row['customerUses'],
            ];
        }
        return $usage;
    }
}
