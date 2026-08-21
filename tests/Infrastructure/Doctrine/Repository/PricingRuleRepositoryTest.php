<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Repository;

use App\Application\Repository\PricingRuleRepositoryInterface;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class PricingRuleRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private PricingRuleRepositoryInterface $repository;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var PricingRuleRepositoryInterface $repository */
        $repository = $container->get(PricingRuleRepositoryInterface::class);
        $this->repository = $repository;
    }

    private function rule(string $name, string $status = PricingRule::STATUS_ACTIVE, int $priority = 0): PricingRule
    {
        return new PricingRule(
            id: new Ulid(),
            name: $name,
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 500,
            priority: $priority,
            status: $status,
        );
    }

    public function testFindActiveOnlyReturnsActiveRules(): void
    {
        $active = $this->rule('Active rule');
        $disabled = $this->rule('Disabled rule', PricingRule::STATUS_DISABLED);
        $this->em->persist($active);
        $this->em->persist($disabled);
        $this->em->flush();

        $found = $this->repository->findActive();
        $foundIds = array_map(static fn(PricingRule $r): string => (string) $r->id, $found);

        static::assertContains((string) $active->id, $foundIds);
        static::assertNotContains((string) $disabled->id, $foundIds);
    }

    public function testFindAllForAdminOrdersActiveFirstThenByPriorityDesc(): void
    {
        $lowPriorityActive = $this->rule('Low priority active', PricingRule::STATUS_ACTIVE, 1);
        $highPriorityActive = $this->rule('High priority active', PricingRule::STATUS_ACTIVE, 10);
        $disabled = $this->rule('Disabled', PricingRule::STATUS_DISABLED, 99);
        $this->em->persist($lowPriorityActive);
        $this->em->persist($highPriorityActive);
        $this->em->persist($disabled);
        $this->em->flush();

        $all = $this->repository->findAllForAdmin();
        $names = array_map(static fn(PricingRule $r): string => $r->name, $all);

        $highIndex = array_search('High priority active', $names, true);
        $lowIndex = array_search('Low priority active', $names, true);
        $disabledIndex = array_search('Disabled', $names, true);

        static::assertNotFalse($highIndex);
        static::assertNotFalse($lowIndex);
        static::assertNotFalse($disabledIndex);
        static::assertLessThan(
            $lowIndex,
            $highIndex,
            'Higher-priority active rule should sort before the lower-priority one',
        );
        static::assertLessThan($disabledIndex, $lowIndex, 'Active rules should sort before disabled ones');
    }

    public function testPersistedRuleRoundTripsThroughTheCustomAdjustmentTypeColumn(): void
    {
        $rule = $this->rule('Round trip');
        $this->em->persist($rule);
        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->em->find(PricingRule::class, $rule->id);

        static::assertInstanceOf(PricingRule::class, $reloaded);
        static::assertSame(AdjustmentType::FIXED_AMOUNT_OFF, $reloaded->adjustmentType);
    }
}
