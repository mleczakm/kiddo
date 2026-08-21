<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminPricingRulesComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminPricingRulesComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private function freshEntityManager(): EntityManagerInterface
    {
        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $registry->resetManager();

        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreatingARuleThroughTheFormPersistsItAndLogsAnAuditEntry(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminPricingRulesComponent::class, data: [], client: $client);
        $component->call('openCreateModal');
        $component->set('name', 'Wiosenna promocja');
        $component->set('adjustmentValue', '1500');
        $component->call('save');

        $em = $this->freshEntityManager();

        $rules = $em->getRepository(PricingRule::class)->findAll();
        static::assertCount(1, $rules);
        static::assertSame('Wiosenna promocja', $rules[0]->name);
        static::assertSame(1500, $rules[0]->adjustmentValue);
        static::assertTrue($rules[0]->isActive());

        $logs = $em->getRepository(ActivityLog::class)->findBy(['type' => ActivityType::PRICING_RULE_CREATED]);
        static::assertCount(1, $logs);
        static::assertStringContainsString('Wiosenna promocja', $logs[0]->getTitle());
    }

    public function testPromotionCodeIsNormalizedToUppercaseOnSave(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminPricingRulesComponent::class, data: [], client: $client);
        $component->call('openCreateModal');
        $component->set('name', 'Kod powitalny');
        $component->set('adjustmentValue', '1000');
        $component->set('promotionCode', ' welcome10 ');
        $component->call('save');

        $em = $this->freshEntityManager();

        $rules = $em->getRepository(PricingRule::class)->findAll();
        static::assertCount(1, $rules);
        static::assertSame('WELCOME10', $rules[0]->promotionCode);
    }

    public function testSaveRejectsAMissingAdjustmentValueWithoutPersisting(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminPricingRulesComponent::class, data: [], client: $client);
        $component->call('openCreateModal');
        $component->set('name', 'Bez wartości');
        $component->call('save');

        /** @var AdminPricingRulesComponent $result */
        $result = $component->component();
        static::assertTrue($result->isModalOpen, 'Modal should stay open when validation fails');

        $em = $this->freshEntityManager();
        static::assertCount(0, $em->getRepository(PricingRule::class)->findAll());
    }

    public function testEditingARuleUpdatesItAndRecordsAFieldDiff(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $rule = new PricingRule(
            id: new Ulid(),
            name: 'Original name',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 500,
        );
        $em->persist($admin);
        $em->persist($rule);
        $em->flush();
        $client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminPricingRulesComponent::class, data: [], client: $client);
        $component->call('edit', ['id' => (string) $rule->id]);
        $component->set('name', 'Updated name');
        $component->call('save');

        $em = $this->freshEntityManager();

        $reloaded = $em->find(PricingRule::class, $rule->id);
        static::assertInstanceOf(PricingRule::class, $reloaded);
        static::assertSame('Updated name', $reloaded->name);

        $logs = $em->getRepository(ActivityLog::class)->findBy(['type' => ActivityType::PRICING_RULE_UPDATED]);
        static::assertCount(1, $logs);
        static::assertStringContainsString('Original name', (string) $logs[0]->getSummary());
        static::assertStringContainsString('Updated name', (string) $logs[0]->getSummary());
    }

    public function testDisablingARuleSetsItsStatusAndLogsTheChange(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $rule = new PricingRule(
            id: new Ulid(),
            name: 'To disable',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 500,
        );
        $em->persist($admin);
        $em->persist($rule);
        $em->flush();
        $client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminPricingRulesComponent::class, data: [], client: $client);
        $component->call('disable', ['id' => (string) $rule->id]);

        $em = $this->freshEntityManager();

        $reloaded = $em->find(PricingRule::class, $rule->id);
        static::assertInstanceOf(PricingRule::class, $reloaded);
        static::assertFalse($reloaded->isActive());

        $logs = $em->getRepository(ActivityLog::class)->findBy(['type' => ActivityType::PRICING_RULE_DISABLED]);
        static::assertCount(1, $logs);
    }

    public function testExclusivityWarningFlagsAnotherActiveRuleInTheSameGroup(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $existing = new PricingRule(
            id: new Ulid(),
            name: 'Seasonal rule',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 500,
            stackable: false,
            exclusivityGroup: 'seasonal',
        );
        $em->persist($admin);
        $em->persist($existing);
        $em->flush();
        $client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminPricingRulesComponent::class, data: [], client: $client);
        $component->call('openCreateModal');
        $component->set('stackable', false);
        $component->set('exclusivityGroup', 'seasonal');

        /** @var AdminPricingRulesComponent $result */
        $result = $component->component();
        $warning = $result->getExclusivityWarning();

        static::assertNotNull($warning);
        static::assertStringContainsString('Seasonal rule', $warning);
    }

    public function testDeniesAccessWithoutTheManagePricingRole(): void
    {
        $client = static::createClient();
        $client->catchExceptions(false);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $em->persist($host);
        $em->flush();
        $client->loginUser($host);

        $component = $this->createLiveComponent(name: AdminPricingRulesComponent::class, data: [], client: $client);

        $this->expectException(AccessDeniedException::class);
        $component->call('openCreateModal');
    }
}
