<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Payment;

use App\Application\Service\Payment\TransferReviewThresholdProvider;
use App\Entity\Setting;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class TransferReviewThresholdProviderTest extends KernelTestCase
{
    public function testDefaultsToOneThousandPlnWhenNoSettingIsStored(): void
    {
        self::bootKernel();

        /** @var TransferReviewThresholdProvider $provider */
        $provider = self::getContainer()->get(TransferReviewThresholdProvider::class);

        static::assertTrue($provider->get()->isEqualTo(Money::of(1000, 'PLN')));
    }

    public function testUsesTheAdminConfiguredThresholdWhenSet(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $setting = new Setting();
        $setting->setKey(TransferReviewThresholdProvider::SETTING_KEY);
        $setting->setContent([
            'amount_pln' => 2500,
        ]);
        $em->persist($setting);
        $em->flush();

        /** @var TransferReviewThresholdProvider $provider */
        $provider = self::getContainer()->get(TransferReviewThresholdProvider::class);

        static::assertTrue($provider->get()->isEqualTo(Money::of(2500, 'PLN')));
    }
}
