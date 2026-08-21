<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\PricingRuleRepositoryInterface;
use App\Domain\Commerce\Pricing\PricingRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingRule>
 */
class PricingRuleRepository extends ServiceEntityRepository implements PricingRuleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingRule::class);
    }

    #[\Override]
    public function findActive(): array
    {
        return $this->findBy(['status' => PricingRule::STATUS_ACTIVE]);
    }

    #[\Override]
    public function findAllForAdmin(): array
    {
        return $this
            ->createQueryBuilder('r')
            ->orderBy('r.status', 'ASC')
            ->addOrderBy('r.priority', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
