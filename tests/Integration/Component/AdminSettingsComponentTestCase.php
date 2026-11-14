<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\FinanceContactRepository;
use App\Infrastructure\Doctrine\Repository\SettingRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

abstract class AdminSettingsComponentTestCase extends WebTestCase
{
    use InteractsWithLiveComponents;

    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected UserRepository $userRepository;

    protected FinanceContactRepository $financeContactRepository;

    protected SettingRepository $settingRepository;

    protected User $adminUser;

    protected User $regularUser;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        static::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $userRepository = static::getContainer()->get(UserRepository::class);
        static::assertInstanceOf(UserRepository::class, $userRepository);
        $this->userRepository = $userRepository;
        $financeContactRepository = static::getContainer()->get(FinanceContactRepository::class);
        static::assertInstanceOf(FinanceContactRepository::class, $financeContactRepository);
        $this->financeContactRepository = $financeContactRepository;
        $settingRepository = static::getContainer()->get(SettingRepository::class);
        static::assertInstanceOf(SettingRepository::class, $settingRepository);
        $this->settingRepository = $settingRepository;

        // Create test users
        $this->adminUser = new User('admin@test.com', 'Admin User');
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($this->adminUser);

        $this->regularUser = new User('user@test.com', 'Regular User');
        $this->entityManager->persist($this->regularUser);

        $this->entityManager->flush();
    }
}
