<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\PlatformBillingService;
use App\Entity\Setting;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class PlatformBillingServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private PlatformBillingService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(PlatformBillingService::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()
            ->rollBack();
        parent::tearDown();
    }

    public function testGetBillingDataReturnsDefaultWhenNoSettingExists(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = $this->entityManager->getRepository(Setting::class)->findOneBy([
            'key' => 'platform_billing',
        ]);
        if ($setting) {
            $this->entityManager->remove($setting);
            $this->entityManager->flush();
        }

        $result = $this->service->getBillingData();

        $this->assertEquals([
            'currentDue' => '0.00',
            'pastDue' => '0.00',
        ], $result);
    }

    public function testGetBillingDataReturnsExistingData(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.50',
            'pastDue' => '50.25',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $result = $this->service->getBillingData();

        $this->assertEquals([
            'currentDue' => '100.50',
            'pastDue' => '50.25',
        ], $result);
    }

    public function testAddCommissionToCurrentDue(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.00',
            'pastDue' => '0.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $paymentAmount = Money::of('1000.00', 'PLN');
        $this->service->addCommissionToCurrentDue($paymentAmount);

        $this->entityManager->refresh($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        $this->assertEquals('120.00', $updatedContent['currentDue']); // 100 + (1000 * 0.02) = 120
        $this->assertEquals('0.00', $updatedContent['pastDue']);
    }

    public function testAddCommissionToCurrentDueCreatesNewSetting(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = $this->entityManager->getRepository(Setting::class)->findOneBy([
            'key' => 'platform_billing',
        ]);
        if ($setting) {
            $this->entityManager->remove($setting);
            $this->entityManager->flush();
        }

        $paymentAmount = Money::of('500.00', 'PLN');
        $this->service->addCommissionToCurrentDue($paymentAmount);

        $setting = $this->entityManager->getRepository(Setting::class)->findOneBy([
            'key' => 'platform_billing',
        ]);
        $this->assertNotNull($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        $this->assertEquals('10.00', $updatedContent['currentDue']); // 0 + (500 * 0.02) = 10
        $this->assertEquals('0.00', $updatedContent['pastDue']);
    }

    public function testProcessPastDuePayment(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.00',
            'pastDue' => '50.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $this->service->processPastDuePayment(30.00);

        $this->entityManager->refresh($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        $this->assertEquals('100.00', $updatedContent['currentDue']);
        $this->assertEquals('20.00', $updatedContent['pastDue']); // 50 - 30 = 20
    }

    public function testProcessPastDuePaymentWithExcess(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.00',
            'pastDue' => '50.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $this->service->processPastDuePayment(70.00);

        $this->entityManager->refresh($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        $this->assertEquals('80.00', $updatedContent['currentDue']); // 100 - (70 - 50) = 80
        $this->assertEquals('0.00', $updatedContent['pastDue']);
    }

    public function testProcessPastDuePaymentWithExcessToNegative(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '10.00',
            'pastDue' => '50.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $this->service->processPastDuePayment(70.00);

        $this->entityManager->refresh($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        $this->assertEquals('-10.00', $updatedContent['currentDue']); // 10 - (70 - 50) = -10
        $this->assertEquals('0.00', $updatedContent['pastDue']);
    }

    public function testSetPastDueAsPaid(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.00',
            'pastDue' => '50.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $this->service->setPastDueAsPaid();

        $this->entityManager->refresh($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        $this->assertEquals('50.00', $updatedContent['currentDue']); // 100 - 50 = 50
        $this->assertEquals('0.00', $updatedContent['pastDue']);
    }

    public function testGetCurrentDue(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.50',
            'pastDue' => '0.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $result = $this->service->getCurrentDue();

        $this->assertTrue($result->isEqualTo(Money::of('100.50', 'PLN')));
    }

    public function testGetPastDue(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '0.00',
            'pastDue' => '50.25',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $result = $this->service->getPastDue();

        $this->assertTrue($result->isEqualTo(Money::of('50.25', 'PLN')));
    }

    public function testHasPastDueReturnsTrueWhenPastDueExists(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '0.00',
            'pastDue' => '50.25',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $this->assertTrue($this->service->hasPastDue());
    }

    public function testHasPastDueReturnsFalseWhenNoPastDue(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.00',
            'pastDue' => '0.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $this->assertFalse($this->service->hasPastDue());
    }
}
