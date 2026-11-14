<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Entity\Setting;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class PlatformBillingServiceTest extends PlatformBillingServiceTestCase
{
    public function testGetBillingDataReturnsDefaultWhenNoSettingExists(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
        $setting = $this->entityManager
            ->getRepository(Setting::class)
            ->findOneBy([
                'key' => 'platform_billing',
            ]);
        if ($setting) {
            $this->entityManager->remove($setting);
            $this->entityManager->flush();
        }

        $result = $this->service->getBillingData();

        static::assertEquals(
            [
                'currentDue' => '0.00',
                'pastDue' => '0.00',
            ],
            $result,
        );
    }

    public function testGetBillingDataReturnsExistingData(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.50',
            'pastDue' => '50.25',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $result = $this->service->getBillingData();

        static::assertEquals(
            [
                'currentDue' => '100.50',
                'pastDue' => '50.25',
            ],
            $result,
        );
    }

    public function testGetBillingDataFallsBackToZeroForMalformedStoredAmounts(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => 'not-a-number',
            'pastDue' => null,
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        static::assertSame(
            [
                'currentDue' => '0.00',
                'pastDue' => '0.00',
            ],
            $this->service->getBillingData(),
        );
    }

    public function testAddCommissionToCurrentDue(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
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
        static::assertSame('120.00', $updatedContent['currentDue']); // 100 + (1000 * 0.02) = 120
        static::assertSame('0.00', $updatedContent['pastDue']);
    }

    public function testAddCommissionToCurrentDueCreatesNewSetting(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
        $setting = $this->entityManager
            ->getRepository(Setting::class)
            ->findOneBy([
                'key' => 'platform_billing',
            ]);
        if ($setting) {
            $this->entityManager->remove($setting);
            $this->entityManager->flush();
        }

        $paymentAmount = Money::of('500.00', 'PLN');
        $this->service->addCommissionToCurrentDue($paymentAmount);

        $setting = $this->entityManager
            ->getRepository(Setting::class)
            ->findOneBy([
                'key' => 'platform_billing',
            ]);
        static::assertNotNull($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        static::assertSame('10.00', $updatedContent['currentDue']); // 0 + (500 * 0.02) = 10
        static::assertSame('0.00', $updatedContent['pastDue']);
    }

    public function testGetCurrentDue(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.50',
            'pastDue' => '0.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $result = $this->service->getCurrentDue();

        static::assertTrue($result->isEqualTo(Money::of('100.50', 'PLN')));
    }

    public function testGetPastDue(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '0.00',
            'pastDue' => '50.25',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $result = $this->service->getPastDue();

        static::assertTrue($result->isEqualTo(Money::of('50.25', 'PLN')));
    }

    public function testHasPastDueReturnsTrueWhenPastDueExists(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '0.00',
            'pastDue' => '50.25',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        static::assertTrue($this->service->hasPastDue());
    }

    public function testHasPastDueReturnsFalseWhenNoPastDue(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.00',
            'pastDue' => '0.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        static::assertFalse($this->service->hasPastDue());
    }
}
