<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\OrganizationDetailsProvider;
use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class OrganizationDetailsProviderTest extends KernelTestCase
{
    public function testFallsBackToHistoricalDefaultsWhenNothingIsConfigured(): void
    {
        self::bootKernel();

        /** @var OrganizationDetailsProvider $provider */
        $provider = self::getContainer()->get(OrganizationDetailsProvider::class);
        $org = $provider->get();

        static::assertSame('Warsztatownia Sensoryczna', $org->name);
        static::assertSame('46 2490 0005 0000 4000 1897 5420', $org->bankAccount);
        static::assertSame('571 531 213', $org->blikPhone);
        static::assertSame('Aleja Jana Pawła II 12D, 05-250 Radzymin', $org->addressLine());
    }

    public function testUsesAdminConfiguredValuesAndKeepsDefaultsForBlankFields(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $setting = new Setting();
        $setting->setKey(OrganizationDetailsProvider::SETTING_KEY);
        $setting->setContent([
            'name' => 'Nowa Warsztatownia',
            'street' => 'ul. Testowa 1',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'email' => 'kontakt@example.com',
            'phone' => '+48 500 000 000',
            'bank_account' => '',
            'blik_phone' => '   ',
        ]);
        $em->persist($setting);
        $em->flush();

        /** @var OrganizationDetailsProvider $provider */
        $provider = self::getContainer()->get(OrganizationDetailsProvider::class);
        $org = $provider->get();

        static::assertSame('Nowa Warsztatownia', $org->name);
        static::assertSame('kontakt@example.com', $org->email);
        static::assertSame('ul. Testowa 1, 00-001 Warszawa', $org->addressLine());
        // Blank / whitespace-only values fall back to the historical defaults.
        static::assertSame('46 2490 0005 0000 4000 1897 5420', $org->bankAccount);
        static::assertSame('571 531 213', $org->blikPhone);
    }
}
