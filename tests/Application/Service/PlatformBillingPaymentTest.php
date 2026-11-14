<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Entity\Setting;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class PlatformBillingPaymentTest extends PlatformBillingServiceTestCase
{
    public function testProcessPastDuePayment(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
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
        static::assertSame('100.00', $updatedContent['currentDue']);
        static::assertSame('20.00', $updatedContent['pastDue']); // 50 - 30 = 20
    }

    public function testProcessPastDuePaymentWithExcess(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
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
        static::assertSame('80.00', $updatedContent['currentDue']); // 100 - (70 - 50) = 80
        static::assertSame('0.00', $updatedContent['pastDue']);
    }

    public function testProcessPastDuePaymentWithExcessToNegative(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
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
        static::assertSame('-10.00', $updatedContent['currentDue']); // 10 - (70 - 50) = -10
        static::assertSame('0.00', $updatedContent['pastDue']);
    }

    public function testSetPastDueAsPaid(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
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
        static::assertSame('50.00', $updatedContent['currentDue']); // 100 - 50 = 50
        static::assertSame('0.00', $updatedContent['pastDue']);
    }
}
