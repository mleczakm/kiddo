<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Setting;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
class PlatformBillingAlertTest extends WebTestCase
{
    public function testBillingAlertIsVisibleWhenUnpaid(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        // Set past due amount to show the alert
        $this->setBillingPastDue($client, 100.50);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.text-red-400');
        $this->assertSelectorTextContains('div', 'Zaległość');
    }

    public function testBillingAlertIsNotVisibleWhenPaid(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        // Set no past due amount
        $this->setBillingPastDue($client, 0.00);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('div', 'Brak zaległości');
    }

    private function createAdminUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()
            ->get(EntityManagerInterface::class);

        $user = UserAssembler::new()
            ->withEmail('admin_billing@test.com')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function setBillingPastDue(KernelBrowser $client, float $pastDue): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()
            ->get(EntityManagerInterface::class);

        $setting = $entityManager->getRepository(Setting::class)->findOneBy([
            'key' => 'platform_billing',
        ]);
        if (! $setting) {
            $setting = new Setting();
            $setting->setKey('platform_billing');
        }

        $setting->setContent([
            'currentDue' => '0.00',
            'pastDue' => number_format($pastDue, 2, '.', ''),
        ]);

        $entityManager->persist($setting);
        $entityManager->flush();
    }
}
